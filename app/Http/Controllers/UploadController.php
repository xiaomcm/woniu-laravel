<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use App\Core\Auth;
use App\Core\Log as Logger;
use App\Core\Request;
use App\Core\Setting;
use App\Models\Album;
use App\Models\Group;
use App\Models\Storage as StorageModel;
use App\Services\Links;
use App\Services\UploadException;
use App\Services\UploadService;

class UploadController extends Controller
{
    public function index(): HttpResponse
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login?redirect=' . urlencode('/upload'));
        }
        return $this->view('upload', [
            'pageTitle' => '上传图片',
            'albums'    => Album::allForUser((int) $user['id']),
            'storages'  => Group::storages((int) $user['group_id']) ?: StorageModel::enabled(),
            'maxSize'   => Setting::int('upload_max_size', 5242880),
            'allowed'   => (string) Setting::get('upload_allowed', ''),
        ]);
    }



    public function store(): void
    {
        $this->validateCsrf();

        $user = Auth::user();
        if ($user === null && !Setting::bool('guest_upload', false)) {
            $this->api(false, '请先登录', null, 401);
        }

        $files = Request::files('files', 'file');
        $url   = (string) Request::input('url', '');
        $albumId = Request::int('album_id');
        $storageId = Request::int('storage_id', 0);

        if ($files === [] && $url === '') {
            $this->api(false, '没有收到文件');
        }

        $service = new UploadService($user, $albumId, $storageId);
        $items = [];
        $errors = [];

        if ($files === [] && $url !== '') {
            try {
                $items[] = Links::apiPayload($service->uploadFromUrl($url));
            } catch (UploadException $e) {
                $errors[] = $e->getMessage();
            } catch (\Throwable $e) {
                Logger::error($e);
                $errors[] = '抓取失败';
            }
        }

        foreach ($files as $file) {
            try {
                $image = $service->upload($file);
                $items[] = Links::apiPayload($image);
            } catch (UploadException $e) {
                $errors[] = ($file['name'] !== '' ? $file['name'] . '：' : '') . $e->getMessage();
            } catch (\Throwable $e) {
                Logger::error($e);
                $errors[] = ($file['name'] !== '' ? $file['name'] . '：' : '') . '上传失败';
            }
        }

        if ($items === []) {
            $this->api(false, implode('；', $errors));
        }

        $message = '上传成功';
        if ($errors !== []) {
            $message = '部分文件上传失败：' . implode('；', $errors);
        } elseif ((int) $items[0]['status'] === 0) {
            $message = '上传成功，等待管理员审核';
        }

        $this->api(true, $message, $items);
    }
}
