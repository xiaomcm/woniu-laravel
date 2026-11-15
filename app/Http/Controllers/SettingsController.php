<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Token;
use App\Models\User;
use App\Services\StatsService;

class SettingsController extends Controller
{
    public function index(): HttpResponse
    {
        $user = Auth::requireLogin();
        $stats = StatsService::userOverview((int) $user['id']);
        $newToken = Session::get('_new_token');
        Session::forget('_new_token');
        return $this->view('settings', [
            'pageTitle' => '个人设置',
            'user'      => $user,
            'group'     => User::group($user),
            'stats'     => $stats,
            'tokens'    => Token::allForUser((int) $user['id']),
            'newToken'  => $newToken,
        ]);
    }

    public function updateProfile(): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();

        // 用户名注册后不允许修改，这里只更新邮箱
        $data = [
            'email' => (string) Request::input('email', ''),
            '__id'  => (int) $user['id'],
        ];
        $validator = new Validator($data);
        $validator->validate([
            'email' => 'required|email|max:128|unique:users,email',
        ]);
        if ($validator->fails()) {
            flash('error', $validator->firstError());
            redirect('/settings');
        }

        User::updateById((int) $user['id'], ['email' => $data['email']]);
        Auth::refresh();
        flash('success', '资料已更新');
        redirect('/settings');
    }

    public function updatePassword(): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();

        $old = (string) Request::input('old_password', '');
        $new = (string) Request::input('password', '');
        $confirm = (string) Request::input('password_confirmation', '');

        if (!password_verify($old, (string) $user['password'])) {
            flash('error', '原密码不正确');
            redirect('/settings');
        }
        if (mb_strlen($new) < 6) {
            flash('error', '新密码至少 6 位');
            redirect('/settings');
        }
        if ($new !== $confirm) {
            flash('error', '两次输入的新密码不一致');
            redirect('/settings');
        }

        User::updateById((int) $user['id'], ['password' => $new]);
        flash('success', '密码已修改');
        redirect('/settings');
    }

    public function createToken(): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();
        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            $name = 'API 令牌';
        }
        $expires = (string) Request::input('expires_at', '');
        $expiresAt = null;
        if ($expires !== '') {
            $ts = strtotime($expires);
            if ($ts !== false && $ts > time()) {
                $expiresAt = date('Y-m-d H:i:s', $ts);
            }
        }
        $plain = Token::create((int) $user['id'], mb_substr($name, 0, 40), $expiresAt);
        Session::put('_new_token', $plain);
        flash('success', '令牌已创建，请立即复制保存，之后不再显示完整值');
        redirect('/settings');
    }

    public function deleteToken(string $id): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();
        Token::deleteById((int) $id, (int) $user['id']);
        flash('success', '令牌已删除');
        redirect('/settings');
    }
}
