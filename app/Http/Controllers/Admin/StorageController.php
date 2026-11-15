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
use App\Core\Database as DB;
use App\Core\Request;
use App\Models\Storage as StorageModel;
use App\Storage\Manager;

class StorageController extends Controller
{
    public function index(): HttpResponse
    {
        Auth::requireAdmin();
        return $this->view('admin/storages', [
            'pageTitle' => '存储策略',
            'storages'  => StorageModel::allRows(),
            'types'     => StorageModel::types(),
        ], 'layouts/admin');
    }

    public function store(): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();
        $id = StorageModel::create($this->payload());
        flash('success', '存储策略已创建');
        redirect('/admin/storages');
    }

    public function update(string $id): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();
        if (StorageModel::find((int) $id) === null) {
            abort(404, '存储策略不存在');
        }
        StorageModel::updateById((int) $id, $this->payload());
        flash('success', '存储策略已更新');
        redirect('/admin/storages');
    }

    public function delete(string $id): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();
        if (StorageModel::imageCount((int) $id) > 0) {
            flash('error', '该策略下还有图片，无法删除');
            redirect('/admin/storages');
        }
        StorageModel::deleteById((int) $id);
        flash('success', '存储策略已删除');
        redirect('/admin/storages');
    }



    public function test(): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();

        $id = Request::int('id');
        if ($id > 0) {
            $storage = StorageModel::find($id);
            if ($storage === null) {
                $this->api(false, '存储策略不存在');
            }
        } else {
            $storage = [
                'id'           => 0,
                'name'         => '临时测试',
                'type'         => (string) Request::input('type', 'local'),
                'url'          => (string) Request::input('url', ''),
                'config_array' => $this->configFromRequest(),
            ];
        }

        $result = Manager::make($storage)->test();
        $this->api((bool) $result['ok'], (string) $result['message']);
    }

    private function payload(): array
    {
        $isDefault = Request::bool('is_default', false);
        if ($isDefault) {
            DB::query('UPDATE ' . DB::table('storages') . ' SET is_default = 0');
        }
        $type = (string) Request::input('type', 'local');
        $types = StorageModel::types();

        return [
            'name'     => mb_substr(trim((string) Request::input('name', '')), 0, 40) ?: '未命名',
            'type'     => isset($types[$type]) ? $type : 'local',
            'config'   => json_encode($this->configFromRequest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'url'      => rtrim(trim((string) Request::input('url', '')), '/'),
            'is_default' => $isDefault ? 1 : 0,
            'status'   => Request::bool('status', true) ? 1 : 0,
        ];
    }

    private function configFromRequest(): array
    {
        $raw = Request::input('config', []);
        if (!is_array($raw)) {
            $raw = [];
        }
        $config = [];
        foreach ($raw as $key => $value) {
            $key = preg_replace('/[^a-z0-9_]/i', '', (string) $key);
            if ($key === '' || $key === null) {
                continue;
            }
            if (is_array($value)) {
                continue;
            }
            $config[$key] = is_string($value) ? trim($value) : $value;
        }


        if (isset($raw['path_style'])) {
            $config['path_style'] = (string) $raw['path_style'] === '1' ? '1' : '';
        }
        return $config;
    }
}
