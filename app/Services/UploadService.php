<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database as DB;
use App\Core\Setting;
use App\Models\Album;
use App\Models\Group;
use App\Models\Image;
use App\Models\Storage as StorageModel;
use App\Models\User;
use App\Storage\Driver;
use App\Storage\Manager;

class UploadService
{
    private ?array $user;
    private ?array $group;
    private ?int $albumId;
    private ?int $storageId;

    public function __construct(?array $user, ?int $albumId = null, ?int $storageId = null)
    {
        $this->user = $user;
        $this->albumId = $albumId !== null && $albumId > 0 ? $albumId : null;
        $this->storageId = $storageId !== null && $storageId > 0 ? $storageId : null;

        if ($user !== null) {
            $this->group = Group::find((int) $user['group_id']);
        } else {
            $this->group = Group::defaultGroup();
        }
    }

    public function setAlbumId(?int $albumId): void
    {
        $this->albumId = $albumId !== null && $albumId > 0 ? $albumId : null;
    }

    public function setStorageId(?int $storageId): void
    {
        $this->storageId = $storageId !== null && $storageId > 0 ? $storageId : null;
    }



    public function upload(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadException($this->uploadErrorMessage($error));
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new UploadException('临时文件不存在，上传中断');
        }
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp)) {
            throw new UploadException('非法的上传文件');
        }
        $binary = @file_get_contents($tmp);
        if ($binary === false || $binary === '') {
            throw new UploadException('读取上传文件失败');
        }
        return $this->store($binary, (string) ($file['name'] ?? 'image'));
    }



    public function uploadFromUrl(string $url): array
    {
        if (!preg_match('#^https?://#i', $url)) {
            throw new UploadException('图片地址必须以 http(s):// 开头');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 WONIU/2.0',
        ]);
        $binary = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if (!is_string($binary) || $binary === '' || $status >= 400) {
            throw new UploadException('抓取远程图片失败：' . ($error !== '' ? $error : 'HTTP ' . $status));
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        $name = basename($path);
        if ($name === '' || strpos($name, '.') === false) {
            $name = 'remote_' . time() . '.jpg';
        }
        return $this->store($binary, $name);
    }



    public function store(string $binary, string $originalName): array
    {
        $size = strlen($binary);
        $extension = $this->extensionOf($originalName, $binary);
        $this->assertExtension($extension);
        $this->assertSize($size);
        $this->assertQuota($size);

        $isSvg = $extension === 'svg';
        if ($isSvg) {
            if (stripos($binary, '<svg') === false) {
                throw new UploadException('SVG 文件内容无效');
            }
            $info = ImageProcessor::svgInfo($binary);
        } else {
            $info = ImageProcessor::info($binary);
            if ($info === null) {
                throw new UploadException('文件不是有效的图片');
            }
            $detected = ImageProcessor::extensionFromMime($info['mime']);
            if ($detected !== '') {
                $extension = $detected;
                $this->assertExtension($extension);
            }
        }

        $md5  = md5($binary);
        $sha1 = sha1($binary);



        if (Setting::bool('upload_dedupe', true) && $this->user !== null) {
            $existing = Image::findBySha1($sha1, (int) $this->user['id']);
            if ($existing !== null && (int) $existing['status'] === Image::STATUS_APPROVED) {
                return $existing;
            }
        }

        $storage = $this->resolveStorage();
        $mime = $isSvg ? 'image/svg+xml' : (string) $info['mime'];
        $width  = (int) ($info['width'] ?? 0);
        $height = (int) ($info['height'] ?? 0);
        $quality = Setting::int('upload_quality', 92);
        $maxWidth = Setting::int('upload_max_width', 0);

        if ($quality > 0 || $maxWidth > 0) {
            $optimized = ImageProcessor::optimize($binary, $mime, $maxWidth, $quality > 0 ? $quality : 92);
            $binary = $optimized['binary'];
            $width  = $optimized['width'] > 0 ? $optimized['width'] : $width;
            $height = $optimized['height'] > 0 ? $optimized['height'] : $height;
            $size   = strlen($binary);
        }

        if (Setting::bool('watermark_enabled', false)) {
            $binary = ImageProcessor::watermark($binary, $mime, [
                'text'     => (string) Setting::get('watermark_text', ''),
                'image'    => (string) Setting::get('watermark_image', ''),
                'position' => (string) Setting::get('watermark_position', 'bottom-right'),
                'opacity'  => Setting::int('watermark_opacity', 45),
                'size'     => Setting::int('watermark_size', 18),
            ]);
            $size = strlen($binary);
        }

        $pathname = $this->buildPath($extension, $md5, $sha1, $originalName);

        if (!$storage->put($pathname, $binary)) {
            throw new UploadException('写入存储失败，请检查「' . $storage->name() . '」配置');
        }

        $thumbUrl = $this->makeThumbnail($binary, $mime, $sha1, $width, $height);

        $status = Setting::bool('review_enabled', false) ? Image::STATUS_PENDING : Image::STATUS_APPROVED;
        if ($this->user !== null && (int) $this->user['is_admin'] === 1) {
            $status = Image::STATUS_APPROVED;
        }

        $id = Image::create([
            'user_id'       => $this->user === null ? 0 : (int) $this->user['id'],
            'album_id'      => $this->albumId,
            'storage_id'    => $storage->id(),
            'name'          => $this->displayName($originalName, $extension),
            'original_name' => mb_substr($originalName, 0, 200),
            'pathname'      => $pathname,
            'mimetype'      => $mime,
            'extension'     => $extension,
            'size'          => $size,
            'md5'           => $md5,
            'sha1'          => $sha1,
            'width'         => $width,
            'height'        => $height,
            'url'           => $storage->url($pathname),
            'thumb_url'     => $thumbUrl,
            'status'        => $status,
            'is_public'     => 1,
        ]);

        $created = Image::find($id);
        if ($created !== null) {
            $this->applyModeration($created, $storage);
        }

        if ($this->albumId !== null) {
            Album::recount($this->albumId);
        }

        \App\Models\Log::record(
            $this->user === null ? null : (int) $this->user['id'],
            'upload',
            $pathname . ' (' . $size . 'B)'
        );

        return Image::find($id) ?? [];
    }

    /**
     * 走角色组的图片安全驱动器；不通过时按站点策略挂起或直接拒绝
     */
    private function applyModeration(array $image, Driver $storage): void
    {
        $groupId = $this->group === null ? null : (int) $this->group['id'];
        $result = DriveService::audit($groupId, (int) $image['storage_id'], [
            'pathname' => (string) $image['pathname'],
            'url'      => $this->publicUrl($image),
            'name'     => (string) $image['name'],
            'mimetype' => (string) $image['mimetype'],
            'sha1'     => (string) $image['sha1'],
            'width'    => (int) $image['width'],
            'height'   => (int) $image['height'],
            'user_id'  => $this->user === null ? 0 : (int) $this->user['id'],
        ]);

        if ($result->pass) {
            if ((string) $result->message !== '' && (string) $result->message !== '未配置审核驱动') {
                DB::update('images', ['review_note' => mb_substr('自动审核：' . $result->message, 0, 255)], 'id = :id', ['id' => (int) $image['id']]);
            }
            return;
        }

        $action = (string) Setting::get('moderation_action', 'hold');

        if ($action === 'reject') {
            try {
                $storage->delete((string) $image['pathname']);
            } catch (\Throwable $e) {
            }
            if (!empty($image['thumb_url'])) {
                $thumb = BASE_PATH . '/storage/thumbs/' . substr((string) $image['sha1'], 0, 2) . '/' . $image['sha1'] . '.jpg';
                if (is_file($thumb)) {
                    @unlink($thumb);
                }
            }
            Image::forceDeleteById((int) $image['id']);
            throw new UploadException('图片未通过内容审核：' . $result->message);
        }

        DB::update('images', [
            'status'      => Image::STATUS_PENDING,
            'review_note' => mb_substr('自动审核未通过：' . $result->message, 0, 255),
            'updated_at'  => now(),
        ], 'id = :id', ['id' => (int) $image['id']]);
    }

    private function publicUrl(array $image): string
    {
        $url = (string) ($image['url'] ?? '');
        if ($url !== '' && preg_match('#^https?://#i', $url)) {
            return $url;
        }
        return url('uploads/' . $image['pathname']);
    }

    private function resolveStorage(): Driver
    {
        $candidates = [];
        if ($this->storageId !== null && Group::storageAllowed($this->group, $this->storageId)) {
            $candidates[] = $this->storageId;
        }
        if ($this->group !== null && $this->group['default_storage_id'] !== null) {
            $candidates[] = (int) $this->group['default_storage_id'];
        }
        if ($this->group !== null) {
            foreach (Group::storages((int) $this->group['id']) as $row) {
                $candidates[] = (int) $row['id'];
            }
        }
        $default = StorageModel::defaultStorage();
        if ($default !== null) {
            $candidates[] = (int) $default['id'];
        }
        foreach (array_unique($candidates) as $id) {
            $storage = StorageModel::find($id);
            if ($storage !== null && (int) $storage['status'] === 1) {
                return Manager::make($storage);
            }
        }
        throw new UploadException('没有可用的存储策略，请先在后台配置');
    }

    private function extensionOf(string $originalName, string $binary): string
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if ($extension !== '') {
            return $extension;
        }
        $info = ImageProcessor::info($binary);
        if ($info !== null) {
            return ImageProcessor::extensionFromMime($info['mime']);
        }
        return 'jpg';
    }

    private function assertExtension(string $extension): void
    {
        $allowed = array_merge(
            array_filter(array_map('trim', explode(',', strtolower((string) Setting::get('upload_allowed', ''))))),
            $this->group === null ? [] : Group::allowedExtensions($this->group)
        );
        $allowed = array_values(array_unique(array_filter($allowed)));
        if ($allowed !== [] && !in_array($extension, $allowed, true)) {
            throw new UploadException('不支持的文件类型：' . $extension);
        }
    }

    private function assertSize(int $size): void
    {
        $siteLimit = Setting::int('upload_max_size', 5242880);
        if ($siteLimit > 0 && $size > $siteLimit) {
            throw new UploadException('文件大小超出限制（最大 ' . format_bytes($siteLimit) . '）');
        }
        if ($this->group !== null && (int) $this->group['max_file_size'] > 0 && $size > (int) $this->group['max_file_size']) {
            throw new UploadException('当前用户组限制单文件最大 ' . format_bytes((int) $this->group['max_file_size']));
        }
    }

    private function assertQuota(int $size): void
    {
        if ($this->user === null) {
            if (!Setting::bool('guest_upload', false)) {
                throw new UploadException('游客上传已关闭，请先登录');
            }
            return;
        }

        if ($this->group !== null) {
            if (!Group::can($this->group, 'upload')) {
                throw new UploadException('当前用户组未开放上传权限');
            }
            $daily = (int) ($this->group['daily_limit'] ?? 0);
            if ($daily > 0) {
                $today = (int) DB::value(
                    'SELECT COUNT(*) FROM ' . DB::table('images') . '
                     WHERE user_id = :uid AND deleted_at IS NULL AND created_at >= :t',
                    ['uid' => (int) $this->user['id'], 't' => date('Y-m-d 00:00:00')]
                );
                if ($today >= $daily) {
                    throw new UploadException('今日上传已达上限（' . $daily . ' 张），明天再来');
                }
            }
        }

        $capacity = User::capacity($this->user);
        if ($capacity === 0) {
            return;
        }
        $used = User::usedBytes((int) $this->user['id']);
        if ($used + $size > $capacity) {
            throw new UploadException('存储空间不足，已用 ' . format_bytes($used) . ' / ' . format_bytes($capacity));
        }
    }

    private function buildPath(string $extension, string $md5, string $sha1, string $originalName): string
    {
        $format = (string) Setting::get('upload_path_format', '{Y}/{m}/{d}/{sha1}.{ext}');
        $origin = pathinfo($originalName, PATHINFO_FILENAME);
        $origin = preg_replace('/[^\p{L}\p{N}_\-]+/u', '-', (string) $origin) ?: 'image';

        $map = [
            '{Y}'      => date('Y'),
            '{y}'      => date('y'),
            '{m}'      => date('m'),
            '{d}'      => date('d'),
            '{H}'      => date('H'),
            '{i}'      => date('i'),
            '{s}'      => date('s'),
            '{Ymd}'    => date('Ymd'),
            '{ymd}'    => date('ymd'),
            '{md5}'    => $md5,
            '{sha1}'   => $sha1,
            '{uuid}'   => bin2hex(random_bytes(16)),
            '{random}' => str_random(16),
            '{origin}' => mb_substr($origin, 0, 60),
            '{ext}'    => $extension,
        ];

        $path = strtr($format, $map);
        $path = preg_replace_callback('/\{random:(\d+)\}/', static function (array $m): string {
            return str_random(min(64, max(1, (int) $m[1])));
        }, $path) ?? $path;

        if (!preg_match('/\.[a-z0-9]+$/i', $path)) {
            $path .= '.' . $extension;
        }

        $path = str_replace('\\', '/', (string) $path);
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }
            $segments[] = preg_replace('/[^A-Za-z0-9._\-]/', '_', $segment) ?? $segment;
        }
        if ($segments === []) {
            $segments[] = $sha1 . '.' . $extension;
        }
        return implode('/', $segments);
    }

    private function makeThumbnail(string $binary, string $mime, string $sha1, int $width, int $height): ?string
    {
        if (!Setting::bool('thumb_enabled', true)) {
            return null;
        }
        $thumbWidth = Setting::int('thumb_width', 360);
        // 原图比 thumb_width 还窄时也照样生成：列表与广场都拿它当卡片图，
        // 缺了只能退回占位图，比例和真图对不上，整列高度就错位了
        $thumb = ImageProcessor::thumbnail($binary, $mime, $thumbWidth);
        if ($thumb === null) {
            return null;
        }
        $dir = BASE_PATH . '/storage/thumbs/' . substr($sha1, 0, 2);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }
        $file = $dir . '/' . $sha1 . '.jpg';
        if (@file_put_contents($file, $thumb['binary']) === false) {
            return null;
        }
        return url('thumbs/' . substr($sha1, 0, 2) . '/' . $sha1 . '.jpg');
    }

    private function displayName(string $originalName, string $extension): string
    {
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $name = trim((string) $name);
        if ($name === '') {
            $name = 'image';
        }
        return mb_substr($name, 0, 100) . '.' . $extension;
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '文件大小超出服务器限制',
            UPLOAD_ERR_PARTIAL   => '文件只上传了一部分',
            UPLOAD_ERR_NO_FILE   => '没有选择文件',
            UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录',
            UPLOAD_ERR_CANT_WRITE => '写入磁盘失败',
            UPLOAD_ERR_EXTENSION => '上传被 PHP 扩展中断',
            default => '上传失败（错误码 ' . $code . '）',
        };
    }
}
