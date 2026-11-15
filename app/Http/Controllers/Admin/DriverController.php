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
use App\Drivers\Factory;
use App\Drivers\Registry;
use App\Models\Driver as DriverModel;
use App\Models\Group;
use App\Models\Storage as StorageModel;
use App\Services\DriveService;

class DriverController extends Controller
{
    public function index(): HttpResponse
    {
        Auth::requireAdmin();

        $type = (string) Request::input('type', '');
        $types = Registry::types();
        if ($type === '' || !isset($types[$type])) {
            $type = 'security';
        }

        $drivers = DriverModel::allRows($type);
        $providers = Registry::providers($type);
        $allTypes = [];

        foreach ($types as $key => $meta) {
            $allTypes[$key] = $meta;
            $allTypes[$key]['count'] = 0;
        }
        foreach (DriverModel::allRows() as $row) {
            $key = (string) $row['type'];
            if (isset($allTypes[$key])) {
                $allTypes[$key]['count']++;
            }
        }

        $bindings = [];
        foreach ($drivers as $row) {
            $bindings[(int) $row['id']] = [
                'groups'   => DriverModel::ownerIds('group', (int) $row['id']),
                'storages' => DriverModel::ownerIds('storage', (int) $row['id']),
            ];
        }

        return $this->view('admin/drivers', [
            'pageTitle' => '驱动器',
            'type'      => $type,
            'typeMeta'  => $types[$type],
            'types'     => $allTypes,
            'drivers'   => $drivers,
            'providers' => $providers,
            'bindings'  => $bindings,
            'groups'    => Group::allRows(),
            'storages'  => StorageModel::allRows(),
        ], 'layouts/admin');
    }

    public function create(): HttpResponse
    {
        Auth::requireAdmin();

        $type = (string) Request::input('type', 'security');
        $provider = (string) Request::input('provider', '');
        $meta = Registry::provider($type, $provider);
        if ($meta === null) {
            flash('error', '请选择要添加的驱动器类型');
            redirect('admin/drivers?type=' . urlencode($type));
        }

        return $this->view('admin/driver_form', [
            'pageTitle' => '添加' . $meta['name'],
            'type'      => $type,
            'provider'  => $provider,
            'meta'      => $meta,
            'driver'    => null,
            'config'    => Factory::defaults($type, $provider),
            'groups'    => Group::allRows(),
            'storages'  => StorageModel::allRows(),
            'bound'     => ['groups' => [], 'storages' => []],
        ], 'layouts/admin');
    }

    public function edit(string $id): HttpResponse
    {
        $id = (int) $id;
        Auth::requireAdmin();

        $driver = DriverModel::find($id);
        if ($driver === null) {
            abort(404, '驱动器不存在');
        }
        $meta = Registry::provider((string) $driver['type'], (string) $driver['provider']);
        if ($meta === null) {
            abort(404, '驱动器类型未注册');
        }

        $config = array_merge(Factory::defaults((string) $driver['type'], (string) $driver['provider']), DriverModel::config($driver));

        return $this->view('admin/driver_form', [
            'pageTitle' => '编辑 ' . $driver['name'],
            'type'      => (string) $driver['type'],
            'provider'  => (string) $driver['provider'],
            'meta'      => $meta,
            'driver'    => $driver,
            'config'    => $config,
            'groups'    => Group::allRows(),
            'storages'  => StorageModel::allRows(),
            'bound'     => [
                'groups'   => DriverModel::ownerIds('group', $id),
                'storages' => DriverModel::ownerIds('storage', $id),
            ],
        ], 'layouts/admin');
    }

    public function store(): void
    {
        Auth::requireAdmin();
        $this->validateCsrf();

        $type = (string) Request::input('type', '');
        $provider = (string) Request::input('provider', '');
        if (!Factory::supports($type, $provider)) {
            flash('error', '未知的驱动器');
            back();
        }

        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            $name = Registry::label($type, $provider);
        }

        $id = DriverModel::create([
            'name'     => mb_substr($name, 0, 60),
            'type'     => $type,
            'provider' => $provider,
            'config'   => json_encode(Factory::normalizeConfig($type, $provider, Request::all()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status'   => Request::bool('status', true) ? 1 : 0,
            'sortable' => Request::int('sortable', 0),
            'remark'   => mb_substr((string) Request::input('remark', ''), 0, 200),
        ]);

        $this->syncBindings($id);

        flash('success', '驱动器已创建');
        redirect('admin/drivers?type=' . urlencode($type));
    }

    public function update(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        $driver = DriverModel::find($id);
        if ($driver === null) {
            abort(404, '驱动器不存在');
        }

        $type = (string) $driver['type'];
        $provider = (string) $driver['provider'];

        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            $name = Registry::label($type, $provider);
        }

        DriverModel::updateById($id, [
            'name'     => mb_substr($name, 0, 60),
            'config'   => json_encode(Factory::normalizeConfig($type, $provider, Request::all()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status'   => Request::bool('status', false) ? 1 : 0,
            'sortable' => Request::int('sortable', 0),
            'remark'   => mb_substr((string) Request::input('remark', ''), 0, 200),
        ]);

        $this->syncBindings($id);

        flash('success', '驱动器已保存');
        redirect('admin/drivers?type=' . urlencode($type));
    }

    public function delete(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        $driver = DriverModel::find($id);
        DriverModel::deleteById($id);

        flash('success', '驱动器已删除');
        redirect('admin/drivers?type=' . urlencode((string) ($driver['type'] ?? 'security')));
    }

    public function test(): void
    {
        Auth::requireAdmin();
        $this->validateCsrf();

        $id = Request::int('id', 0);
        $driver = $id > 0 ? DriverModel::find($id) : null;
        if ($driver === null) {
            $this->api(false, '驱动器不存在');
        }

        $result = DriveService::test($driver, [
            'to'       => (string) Request::input('to', ''),
            'phone'    => (string) Request::input('phone', ''),
            'url'      => (string) Request::input('url', ''),
            'redirect' => url('oauth/callback'),
        ]);

        $this->api($result->pass, $result->message, $result->raw);
    }

    private function syncBindings(int $driverId): void
    {
        $groupIds = Request::input('group_ids', []);
        $storageIds = Request::input('storage_ids', []);
        DriverModel::syncOwners('group', $driverId, is_array($groupIds) ? $groupIds : []);
        DriverModel::syncOwners('storage', $driverId, is_array($storageIds) ? $storageIds : []);
    }
}
