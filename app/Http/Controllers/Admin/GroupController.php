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
use App\Models\Group;
use App\Models\Storage as StorageModel;

class GroupController extends Controller
{
    public function index(): HttpResponse
    {
        Auth::requireAdmin();
        return $this->view('admin/groups', [
            'pageTitle' => '用户组',
            'groups'    => Group::allRows(),
            'storages'  => StorageModel::allRows(),
        ], 'layouts/admin');
    }

    public function store(): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();

        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            flash('error', '用户组名称不能为空');
            redirect('/admin/groups');
        }

        // 先把其它组降级再插新组，中间不会出现「两个默认组」的窗口
        $payload = $this->payload();
        if ((int) $payload['is_default'] === 1) {
            $this->resetDefault();
        }
        Group::create($payload);
        flash('success', '用户组已创建');
        redirect('/admin/groups');
    }

    public function update(string $id): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();
        $group = Group::find((int) $id);
        if ($group === null) {
            abort(404, '用户组不存在');
        }

        $payload = $this->payload();

        // 默认组和普通组一样可以改名称、容量、格式、存储、权限、水印
        // 唯一拦的是「把系统里最后一个默认组取消掉」——取消后新用户注册只能回落到第一条记录
        if ((int) $payload['is_default'] === 0 && (int) $group['is_default'] === 1) {
            $others = (int) DB::value(
                'SELECT COUNT(*) FROM ' . DB::table('groups') . ' WHERE is_default = 1 AND id <> :id',
                ['id' => (int) $id]
            );
            if ($others === 0) {
                flash('error', '至少要保留一个默认用户组：先把别的组设为默认，或保持「设为默认组」勾选');
                redirect('/admin/groups');
            }
        }

        // 设成默认时先把别的组降级，之后再把本组写成 1，
        // 顺序反了会出现「两个默认组」，Group::defaultGroup() 就可能取错
        if ((int) $payload['is_default'] === 1) {
            $this->resetDefault();
        }
        Group::updateById((int) $id, $payload);
        flash('success', '用户组已更新');
        redirect('/admin/groups');
    }

    /**
     * 把其它组的默认标记清掉，保证系统里始终只有一个默认组
     * 调用点在写库之前，改名/校验失败时不会误伤现有默认组
     */
    private function resetDefault(): void
    {
        DB::query('UPDATE ' . DB::table('groups') . ' SET is_default = 0');
    }

    public function delete(string $id): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();
        $group = Group::find((int) $id);
        if ($group === null) {
            abort(404, '用户组不存在');
        }
        if (Group::userCount((int) $id) > 0) {
            flash('error', '该用户组下还有用户，无法删除');
            redirect('/admin/groups');
        }
        if ((int) $group['is_default'] === 1) {
            flash('error', '默认用户组不能删除');
            redirect('/admin/groups');
        }
        Group::deleteById((int) $id);
        flash('success', '用户组已删除');
        redirect('/admin/groups');
    }

    private function payload(): array
    {
        $allowed = (string) Request::input('allowed_extensions', '');
        $allowed = implode(',', array_filter(array_map(
            static fn ($v) => strtolower(trim($v)),
            explode(',', $allowed)
        )));

        $isDefault = Request::bool('is_default', false);

        $permissions = [];
        foreach (Group::permissionKeys() as $key) {
            $permissions[$key] = Request::bool('perm_' . $key, false) ? 1 : 0;
        }

        return [
            'name'               => mb_substr(trim((string) Request::input('name', '')), 0, 40),
            'is_default'         => $isDefault ? 1 : 0,
            'capacity'           => (int) ((float) Request::input('capacity_mb', '0') * 1024 * 1024),
            'max_file_size'      => (int) ((float) Request::input('max_file_size_mb', '0') * 1024 * 1024),
            'allowed_extensions' => $allowed,
            'default_storage_id' => Request::int('default_storage_id', 0) ?: null,
            'max_upload_num'     => max(1, Request::int('max_upload_num', 10)),
            'daily_limit'        => max(0, Request::int('daily_limit', 0)),
            'permissions'        => json_encode($permissions, JSON_UNESCAPED_UNICODE),
            'watermark'          => Request::bool('watermark', false) ? 1 : 0,
        ];
    }
}
