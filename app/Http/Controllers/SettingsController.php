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
use App\Core\Setting;
use App\Core\Response;
use App\Services\CodeService;
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
            'pageTitle'  => '个人设置',
            'user'       => $user,
            'group'      => User::group($user),
            'stats'      => $stats,
            'tokens'     => Token::allForUser((int) $user['id']),
            'newToken'   => $newToken,
            // 站点总开关关着时，用户侧的 API 开关和令牌管理都要跟着停下
            'siteApiOn'        => Setting::bool('api_enabled', true),
            // 后台可控：改邮箱要不要邮箱验证码
            'emailVerifyOn'    => Setting::bool('email_change_verify', true),
        ]);
    }

    /**
     * 改邮箱时往「新邮箱」发验证码。会话记下目标和用户 id，
     * 验证时按同一邮箱核对，防止拿 A 邮箱的码改到 B 邮箱。
     */
    public function sendEmailChangeCode(): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();
        if (!Setting::bool('email_change_verify', true)) {
            Response::json(['status' => false, 'message' => '当前无需邮箱验证'], 400);
        }
        if (!Setting::bool('mail_enabled', false)) {
            Response::json(['status' => false, 'message' => '站点未开启邮件服务，请联系管理员'], 400);
        }
        $email = trim((string) Request::input('email', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['status' => false, 'message' => '请填写正确的邮箱'], 400);
        }
        if (mb_strtolower($email) === mb_strtolower((string) $user['email'])) {
            Response::json(['status' => false, 'message' => '新邮箱和当前邮箱相同'], 400);
        }
        // 换绑前先验目标邮箱没被别人占用
        $exists = User::findByEmail($email);
        if ($exists !== null && (int) $exists['id'] !== (int) $user['id']) {
            Response::json(['status' => false, 'message' => '该邮箱已被使用'], 400);
        }
        Session::put('_email_change', ['email' => $email, 'user_id' => (int) $user['id']]);
        $result = CodeService::send('mail', $email, 'email_change');
        if (!$result->pass) {
            Response::json(['status' => false, 'message' => $result->message], 429);
        }
        Response::json(['status' => true, 'message' => '验证码已发到新邮箱，请查收']);
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

        // 后台开了验证就必须填对新邮箱收到的码；关掉则保持原来的直接改
        if (Setting::bool('email_change_verify', true)) {
            $pending = Session::get('_email_change');
            $code = trim((string) Request::input('email_code', ''));
            if (!is_array($pending)
                || (int) ($pending['user_id'] ?? 0) !== (int) $user['id']
                || mb_strtolower((string) ($pending['email'] ?? '')) !== mb_strtolower($data['email'])) {
                flash('error', '请先获取新邮箱的验证码');
                redirect('/settings');
            }
            if ($code === '' || !CodeService::verify('mail', $data['email'], 'email_change', $code)) {
                flash('error', '邮箱验证码不正确或已过期');
                redirect('/settings');
            }
            Session::forget('_email_change');
        }

        User::updateById((int) $user['id'], ['email' => $data['email']]);
        Auth::refresh();
        flash('success', '邮箱已更新');
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

    /**
     * 用户自己的 API 总开关。关掉后该账号所有令牌立即失效，但令牌本身保留，
     * 重新打开即可恢复，不用重建令牌。
     */
    public function updateApiEnabled(): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();
        // 站主关了全站 API，用户不能再自己打开——否则开关看着是开的，调接口照样 403
        if (!Setting::bool('api_enabled', true)) {
            flash('error', '本站已关闭 API 接口，请联系站主');
            redirect('/settings');
        }
        $enabled = (string) Request::input('api_enabled', '0') === '1' ? 1 : 0;
        User::updateById((int) $user['id'], ['api_enabled' => $enabled]);
        Auth::refresh();
        flash('success', $enabled === 1 ? 'API 接口已开启' : 'API 接口已关闭，现有令牌暂不可用');
        redirect('/settings');
    }

    public function createToken(): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();
        if (!Setting::bool('api_enabled', true)) {
            flash('error', '本站已关闭 API 接口，无法创建令牌');
            redirect('/settings');
        }
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
