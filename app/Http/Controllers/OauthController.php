<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Session;
use App\Core\Auth;
use App\Core\Database as DB;
use App\Core\Request;
use App\Core\Setting;
use App\Models\Group;
use App\Models\User;
use App\Services\DriveService;

class OauthController extends Controller
{
    public function redirect(int $id): void
    {
        if (!Setting::bool('oauth_enabled', false)) {
            abort(404, '第三方登录未开放');
        }

        $driver = DriveService::socialById($id);
        if ($driver === null) {
            abort(404, '登录方式不可用');
        }

        $state = bin2hex(random_bytes(16));
        Session::put('_oauth_state', $state);
        Session::put('_oauth_driver', $id);

        redirect($driver->authorizeUrl($state, $this->callbackUrl()));
    }

    public function callback(): void
    {
        if (!Setting::bool('oauth_enabled', false)) {
            abort(404, '第三方登录未开放');
        }

        $state = (string) Request::input('state', '');
        $expected = (string) Session::get('_oauth_state', '');
        $driverId = (int) Session::get('_oauth_driver', 0);

        Session::forget('_oauth_state'); Session::forget('_oauth_driver');

        if ($state === '' || $expected === '' || !hash_equals($expected, $state)) {
            flash('error', '登录状态已失效，请重新发起');
            redirect('login');
        }

        $driver = DriveService::socialById($driverId);
        if ($driver === null) {
            flash('error', '登录方式不可用');
            redirect('login');
        }

        $code = (string) Request::input('code', '');
        if ($code === '') {
            flash('error', '未获得授权码，可能取消了授权');
            redirect('login');
        }

        try {
            $profile = $driver->fetchUser($code, $this->callbackUrl());
        } catch (\Throwable $e) {
            flash('error', '第三方登录失败：' . $e->getMessage());
            redirect('login');
        }

        $openid = (string) ($profile['openid'] ?? '');
        if ($openid === '') {
            flash('error', '第三方账号信息不完整');
            redirect('login');
        }

        $provider = (string) ($profile['openid'] ?? '');
        $providerKey = substr($provider, 0, 3);

        $link = DB::fetch(
            'SELECT * FROM ' . DB::table('user_oauth') . ' WHERE provider = :p AND openid = :o',
            ['p' => $providerKey, 'o' => $openid]
        );

        $user = $link === null ? null : User::find((int) $link['user_id']);

        if ($user === null) {
            $email = (string) ($profile['email'] ?? '');
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = strtolower(preg_replace('/[^a-z0-9]/i', '', $openid)) . '@oauth.local';
            }

            $existing = User::findByEmail($email);
            if ($existing !== null) {
                $user = $existing;
            } else {
                if (!Setting::bool('oauth_auto_register', true)) {
                    flash('error', '站点未开启自动注册，请先用邮箱注册并绑定');
                    redirect('login');
                }
                if (!Setting::bool('register_enabled', true)) {
                    flash('error', '站点已关闭注册');
                    redirect('login');
                }

                $group = Group::defaultGroup();
                $id = User::create([
                    'name'     => mb_substr((string) ($profile['nickname'] ?? '新用户'), 0, 40),
                    'email'    => $email,
                    'password' => bin2hex(random_bytes(16)),
                    'group_id' => $group === null ? 1 : (int) $group['id'],
                    'avatar'   => (string) ($profile['avatar'] ?? ''),
                    'status'   => 1,
                ]);
                $user = User::find($id);
            }

            if ($user !== null) {
                try {
                    DB::insert('user_oauth', [
                        'user_id'    => (int) $user['id'],
                        'driver_id'  => $driverId,
                        'provider'   => $providerKey,
                        'openid'     => $openid,
                        'unionid'    => (string) ($profile['unionid'] ?? ''),
                        'nickname'   => mb_substr((string) ($profile['nickname'] ?? ''), 0, 120),
                        'avatar'     => (string) ($profile['avatar'] ?? ''),
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                }
            }
        }

        if ($user === null) {
            flash('error', '账号创建失败，请改用邮箱登录');
            redirect('login');
        }
        if ((int) $user['status'] !== 1) {
            flash('error', '账号已被停用');
            redirect('login');
        }

        Auth::login($user);
        flash('success', '登录成功，欢迎回来');
        redirect('/dashboard');
    }

    private function callbackUrl(): string
    {
        return url('oauth/callback');
    }
}
