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
use App\Core\Validator;
use App\Models\Group;
use App\Models\User;

class UserController extends Controller
{
    public function index(): HttpResponse
    {
        Auth::requireAdmin();
        $page = max(1, Request::int('page', 1));
        $keyword = (string) Request::input('keyword', '');
        $result = User::paginate($keyword, $page, 20);

        return $this->view('admin/users', [
            'pageTitle' => '用户管理',
            'users'     => $result['items'],
            'pager'     => $result,
            'groups'    => Group::allRows(),
            'keyword'   => $keyword,
        ], 'layouts/admin');
    }

    public function store(): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();

        $data = [
            'name'     => (string) Request::input('name', ''),
            'email'    => (string) Request::input('email', ''),
            'password' => (string) Request::input('password', ''),
        ];
        $validator = new Validator($data);
        $validator->validate([
            'name'     => 'required|min:2|max:24|unique:users,name',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:5',
        ]);
        if ($validator->fails()) {
            flash('error', $validator->firstError());
            redirect('/admin/users');
        }
        if (User::isReservedName($data['name'])) {
            flash('error', '该用户名不允许使用，请换一个');
            redirect('/admin/users');
        }

        $capacityMb = Request::input('capacity_mb', '');
        User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'group_id' => Request::int('group_id', 1),
            'capacity' => $capacityMb === '' ? null : (int) ((float) $capacityMb * 1024 * 1024),
            'is_admin' => Request::bool('is_admin', false) ? 1 : 0,
            'status'   => 1,
        ]);

        flash('success', '用户已创建');
        redirect('/admin/users');
    }

    public function update(string $id): void
    {
        $this->validateCsrf();
        $admin = Auth::requireAdmin();
        $user = User::find((int) $id);
        if ($user === null) {
            abort(404, '用户不存在');
        }

        $data = [];
        // 用户名注册后不允许修改，编辑时提交的 name 一律忽略
        $email = (string) Request::input('email', '');
        if ($email !== '' && strcasecmp($email, (string) $user['email']) !== 0) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', '邮箱格式不正确');
                redirect('/admin/users');
            }
            if (User::findByEmail($email) !== null) {
                flash('error', '该邮箱已被占用');
                redirect('/admin/users');
            }
            $data['email'] = strtolower($email);
        }

        $password = (string) Request::input('password', '');
        if ($password !== '') {
            if (mb_strlen($password) < 6) {
                flash('error', '密码至少 6 位');
                redirect('/admin/users');
            }
            $data['password'] = $password;
        }

        if (Request::input('group_id') !== null) {
            $data['group_id'] = Request::int('group_id', 1);
        }
        $capacityMb = Request::input('capacity_mb', '');
        if ($capacityMb !== null) {
            $data['capacity'] = $capacityMb === '' ? null : (int) ((float) $capacityMb * 1024 * 1024);
        }
        if (Request::input('status') !== null) {
            $data['status'] = Request::bool('status', true) ? 1 : 0;
        }
        if (Request::input('is_admin') !== null) {
            if ((int) $user['id'] === (int) $admin['id'] && !Request::bool('is_admin', false)) {
                flash('error', '不能取消自己的管理员权限');
                redirect('/admin/users');
            }
            $data['is_admin'] = Request::bool('is_admin', false) ? 1 : 0;
        }

        User::updateById((int) $id, $data);
        flash('success', '用户已更新');
        redirect('/admin/users');
    }

    public function delete(string $id): void
    {
        $this->validateCsrf();
        $admin = Auth::requireAdmin();
        if ((int) $id === (int) $admin['id']) {
            flash('error', '不能删除自己');
            redirect('/admin/users');
        }
        User::deleteById((int) $id);
        flash('success', '用户已删除');
        redirect('/admin/users');
    }
}
