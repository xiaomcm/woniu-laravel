<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Response as HttpResponse;
use App\Http\Controllers\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Models\Log;

class LogController extends Controller
{
    public function index(): HttpResponse
    {
        Auth::requireAdmin();

        $tab = (string) Request::input('tab', 'db');
        $filterKeys = ['keyword', 'action', 'user_id', 'days'];
        $filters = [
            'keyword' => trim((string) Request::input('keyword', '')),
            'action'  => trim((string) Request::input('action', '')),
            'user_id' => Request::int('user_id', 0),
            'days'    => Request::int('days', 0),
        ];
        $page = max(1, Request::int('page', 1));
        $result = Log::search($filters, $page, 30);

        // 翻页时只带非空筛选条件，避免 URL 越滚越长
        $query = array_filter(
            array_intersect_key($filters, array_flip($filterKeys)),
            static fn ($v) => $v !== '' && $v !== 0
        );
        if ($tab === 'files') {
            $query['tab'] = 'files';
        }

        return $this->view('admin/logs', [
            'pageTitle' => '操作日志',
            'tab'       => $tab,
            'logs'      => $result['items'],
            'pager'     => $result,
            'filters'   => $filters,
            'actions'   => Log::actions(),
            'users'     => \App\Core\Database::fetchAll(
                'SELECT id, name FROM ' . \App\Core\Database::table('users') . ' ORDER BY id ASC'
            ),
            'files'     => $this->logFiles(),
            'query'     => $query,
        ], 'layouts/admin');
    }

    public function destroy(string $id): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();
        $deleted = Log::deleteIds([(int) $id]);
        flash($deleted > 0 ? 'success' : 'error', $deleted > 0 ? '日志已删除' : '日志不存在');
        redirect('admin/logs');
    }

    public function batchDestroy(): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();
        $ids = $this->ids();
        if ($ids === []) {
            flash('error', '请先勾选要删除的日志');
            redirect('admin/logs');
        }
        $deleted = Log::deleteIds($ids);
        flash('success', '已删除 ' . $deleted . ' 条日志');
        redirect('admin/logs');
    }

    public function clear(): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();
        $days = Request::int('days', 0);
        $deleted = Log::clear($days);
        flash('success', $days > 0
            ? '已清理 ' . $days . ' 天前的日志，共 ' . $deleted . ' 条'
            : '已清空全部日志，共 ' . $deleted . ' 条');
        redirect('admin/logs');
    }

    public function fileView(): void
    {
        Auth::requireAdmin();
        $path = $this->resolveFile((string) Request::input('name', ''));
        if ($path === null) {
            abort(404, '日志文件不存在');
        }

        // 只回尾部 800 行，大文件不会把内存吃满
        $lines = [];
        $handle = @fopen($path, 'rb');
        if ($handle !== false) {
            $content = (string) stream_get_contents($handle);
            fclose($handle);
            $all = preg_split('/\r\n|\r|\n/', $content);
            $lines = array_slice($all === false ? [] : $all, -800);
        }

        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo implode("\n", $lines);
        exit;
    }

    public function fileDelete(): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();
        $path = $this->resolveFile((string) Request::input('name', ''));
        if ($path === null) {
            flash('error', '日志文件不存在');
            redirect('admin/logs?tab=files');
        }
        $ok = @unlink($path);
        flash($ok ? 'success' : 'error', $ok ? '日志文件已删除' : '删除失败，检查目录写入权限');
        redirect('admin/logs?tab=files');
    }

    public function fileClear(): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();
        $count = 0;
        foreach ($this->logFiles() as $file) {
            $path = $this->resolveFile((string) $file['name']);
            if ($path !== null && @unlink($path)) {
                $count++;
            }
        }
        flash($count > 0 ? 'success' : 'error', $count > 0 ? '已删除 ' . $count . ' 个日志文件' : '没有可删除的日志文件');
        redirect('admin/logs?tab=files');
    }

    /**
     * 运行日志文件清单（只认 .log，目录内的 index.html 之类不列出来）
     */
    private function logFiles(): array
    {
        $dir = $this->logDir();
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (glob($dir . '/*.log') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $files[] = [
                'name'  => basename($path),
                'size'  => (int) filesize($path),
                'mtime' => date('Y-m-d H:i:s', (int) filemtime($path)),
            ];
        }
        usort($files, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));
        return $files;
    }

    private function logDir(): string
    {
        return BASE_PATH . '/storage/logs';
    }

    /**
     * 文件名白名单 + realpath 前缀校验，避免 ../ 穿越到站点其它目录
     */
    private function resolveFile(string $name): ?string
    {
        $name = basename(trim($name));
        if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+\.log$/', $name)) {
            return null;
        }
        $dir = realpath($this->logDir());
        $path = realpath($this->logDir() . '/' . $name);
        if ($dir === false || $path === false || strncmp($path, $dir, strlen($dir)) !== 0 || !is_file($path)) {
            return null;
        }
        return $path;
    }
}
