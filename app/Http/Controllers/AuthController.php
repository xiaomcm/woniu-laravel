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
use App\Core\Response;
use App\Core\Setting;
use App\Core\Validator;
use App\Models\Group;
use App\Models\Log;
use App\Models\Package;
use App\Models\User;
use App\Services\CaptchaService;
use App\Services\CodeService;
use App\Services\PaymentService;

class AuthController extends Controller
{
    public function loginForm(): HttpResponse
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        $error = (string) Session::get('_login_error', '');
        $ok = (string) Session::get('_login_ok', '');
        Session::forget('_login_error');
        Session::forget('_login_ok');
        return $this->view('auth/login', [
            'pageTitle' => '登录',
            'redirect'  => $this->safeRedirect((string) Request::input('redirect', '')),
            'error'     => $error,
            'ok'        => $ok,
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $this->validateCsrf();

        // 账号支持用户名或邮箱；兼容旧表单字段名 email
        $account = trim((string) (Request::input('account', '') !== '' ? Request::input('account') : Request::input('email', '')));
        $password = (string) Request::input('password', '');
        // 登录后默认进控制台（/ 是游客落地页，登录用户访问会被弹走）
        $redirect = $this->safeRedirect((string) Request::input('redirect', '/dashboard'));



        $fails = (int) Session::get('_login_fails', 0);
        $lockedUntil = (int) Session::get('_login_lock', 0);
        if ($lockedUntil > time()) {
            Session::put('_login_error', '尝试次数过多，请 ' . ($lockedUntil - time()) . ' 秒后再试');
            redirect('/login');
        }

        $validator = new Validator(['account' => $account, 'password' => $password]);
        // 最短 5 位：站点管理员账号用的是 admin（5 位），长度下限必须放得下
        $validator->validate(['account' => 'required|min:2', 'password' => 'required|min:5']);
        if ($validator->fails()) {
            Session::put('_login_error', $validator->firstError());
            redirect('/login');
        }

        $user = Auth::attempt($account, $password);
        if ($user === null) {
            // 密码正确但账号被停用：给专门提示，不算进暴力破解锁定次数。
            // 只有密码也正确才透露这个状态，避免泄露账号是否存在。
            $candidate = User::findForLogin($account);
            if ($candidate !== null
                && password_verify($password, (string) $candidate['password'])
                && (int) $candidate['status'] !== 1) {
                Session::put('_login_error', '该账号已被停用，请联系管理员');
                redirect('/login');
            }
            $fails++;
            Session::put('_login_fails', $fails);
            if ($fails >= 5) {
                Session::put('_login_lock', time() + 60);
                Session::put('_login_fails', 0);
            }
            Session::put('_login_error', '账号或密码错误');
            redirect('/login');
        }

        Session::forget('_login_fails'); Session::forget('_login_lock');
        Auth::login($user);
        Log::record((int) $user['id'], 'login');
        redirect($redirect);
    }

    // ────────────── 找回密码（邮箱验证码）──────────────
    // 用会话记住「哪个用户在重置」，验证码复用 CodeService（10 分钟有效、60 秒间隔、最多试 5 次）。
    // 不建新表：重置是一次性短流程，会话足够，也避免动数据库结构。

    public function forgotForm(): HttpResponse
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        return $this->view('auth/forgot', [
            'pageTitle' => '找回密码',
            'error'     => (string) Session::get('_forgot_error', ''),
        ], 'layouts/auth');
    }

    /** 发送重置验证码。邮箱不存在时也回成功，避免被人枚举账号。 */
    public function forgotSend(): void
    {
        $this->validateCsrf();
        $email = strtolower(trim((string) Request::input('email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['status' => false, 'message' => '请填写正确的邮箱'], 422);
        }
        if (!Setting::bool('mail_enabled', false)) {
            Response::json(['status' => false, 'message' => '站点未开启邮件服务，请联系管理员'], 400);
        }

        $user = User::findByEmail($email);
        if ($user !== null) {
            // 会话先记下邮箱，CodeService 验证时也按这个邮箱核对，防止换码攻击
            Session::put('_forgot_reset', ['email' => $email, 'user_id' => (int) $user['id']]);
            $result = CodeService::send('mail', $email, 'forgot');
            if (!$result->pass) {
                Response::json(['status' => false, 'message' => $result->message], 429);
            }
        }
        // 无论邮箱是否存在都回同样的提示
        Response::json(['status' => true, 'message' => '如果该邮箱已注册，验证码已发送，请查收邮件']);
    }

    public function forgotResetForm(): HttpResponse
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        $reset = Session::get('_forgot_reset');
        if (!is_array($reset) || trim((string) ($reset['email'] ?? '')) === '') {
            Session::put('_forgot_error', '请先填写邮箱获取验证码');
            redirect('/forgot');
        }
        return $this->view('auth/forgot-reset', [
            'pageTitle' => '重置密码',
            'email'     => (string) $reset['email'],
            'error'     => (string) Session::get('_forgot_error', ''),
        ], 'layouts/auth');
    }

    /** 校验验证码并重置密码 */
    public function forgotReset(): void
    {
        $this->validateCsrf();
        $reset = Session::get('_forgot_reset');
        if (!is_array($reset) || trim((string) ($reset['email'] ?? '')) === '') {
            Session::put('_forgot_error', '重置已失效，请重新获取验证码');
            redirect('/forgot');
        }
        $email = (string) $reset['email'];
        $code = trim((string) Request::input('code', ''));
        $password = (string) Request::input('password', '');
        $confirm = (string) Request::input('password_confirmation', '');

        if (!CodeService::verify('mail', $email, 'forgot', $code)) {
            Session::put('_forgot_error', '验证码不正确或已过期');
            redirect('/forgot/reset');
        }
        if (mb_strlen($password) < 6) {
            Session::put('_forgot_error', '新密码至少 6 位');
            redirect('/forgot/reset');
        }
        if ($password !== $confirm) {
            Session::put('_forgot_error', '两次输入的新密码不一致');
            redirect('/forgot/reset');
        }

        $user = User::find((int) ($reset['user_id'] ?? 0));
        if ($user === null || strcasecmp((string) $user['email'], $email) !== 0) {
            Session::forget('_forgot_reset');
            Session::put('_forgot_error', '账号信息已变化，请重新找回');
            redirect('/forgot');
        }
        User::updateById((int) $user['id'], ['password' => $password]);
        Session::forget('_forgot_reset');
        Session::forget('_forgot_error');
        Session::put('_login_ok', '密码已重置，请用新密码登录');
        redirect('/login');
    }

    public function registerForm(): HttpResponse
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        if (!Setting::bool('register_enabled', true)) {
            return $this->view('auth/register', [
                'pageTitle' => '注册',
                'closed'    => true,
                'error'     => '',
                'captcha'   => CaptchaService::frontend(),
            ], 'layouts/auth');
        }
        $error = (string) Session::get('_register_error', '');
        Session::forget('_register_error');
        return $this->view('auth/register', [
            'pageTitle' => '注册',
            'closed'    => false,
            'error'     => $error,
            'captcha'   => CaptchaService::frontend(),
        ], 'layouts/auth');
    }

    /** 注册邮箱验证码发送（仅在开启邮箱验证时可用） */
    public function sendRegisterCode(): void
    {
        $this->validateCsrf();

        if (!Setting::bool('register_enabled', true)) {
            Response::json(['status' => false, 'message' => '当前站点已关闭注册'], 403);
        }
        if (!Setting::bool('register_email_verify', false)) {
            Response::json(['status' => false, 'message' => '当前注册无需邮箱验证'], 400);
        }

        $email = strtolower(trim((string) Request::input('email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['status' => false, 'message' => '邮箱格式不正确'], 422);
        }
        if (User::findByEmail($email) !== null) {
            Response::json(['status' => false, 'message' => '该邮箱已被占用'], 422);
        }

        $result = CodeService::send('mail', $email, 'register');
        Response::json(['status' => $result->pass, 'message' => $result->message], $result->pass ? 200 : 429);
    }

    public function register(): void
    {
        $this->validateCsrf();

        if (!Setting::bool('register_enabled', true)) {
            Session::put('_register_error', '当前站点已关闭注册');
            redirect('/register');
        }

        $data = [
            'name'     => (string) Request::input('name', ''),
            'email'    => (string) Request::input('email', ''),
            'password' => (string) Request::input('password', ''),
            'password_confirmation' => (string) Request::input('password_confirmation', ''),
            'invite'   => (string) Request::input('invite', ''),
        ];

        $validator = new Validator($data);
        $validator->validate([
            'name'     => 'required|min:2|max:24|unique:users,name',
            'email'    => 'required|email|max:128|unique:users,email',
            'password' => 'required|min:5|max:64|confirmed',
        ]);
        if ($validator->fails()) {
            Session::put('_register_error', $validator->firstError());
            redirect('/register');
        }

        if (User::isReservedName($data['name'])) {
            Session::put('_register_error', '该用户名不允许使用，请换一个');
            redirect('/register');
        }

        // 注册验证码（后台全局开关；关闭时整段跳过，完全不校验）
        $captcha = CaptchaService::frontend();
        $phone = trim((string) Request::input('phone', ''));
        if ($captcha['enabled'] ?? false) {
            if (($captcha['kind'] ?? '') === 'sms' && $phone === '') {
                Session::put('_register_error', '请填写手机号');
                redirect('/register');
            }
            $result = CaptchaService::verify([
                'captcha_code'         => (string) Request::input('captcha_code', ''),
                'captcha_ticket'       => (string) Request::input('captcha_ticket', ''),
                'captcha_randstr'      => (string) Request::input('captcha_randstr', ''),
                'captcha_verify_param' => (string) Request::input('captcha_verify_param', ''),
                'captcha_sms_code'     => (string) Request::input('captcha_sms_code', ''),
                'phone'                => $phone,
            ]);
            if (!$result->pass) {
                Session::put('_register_error', $result->message);
                redirect('/register');
            }
        } else {
            $phone = '';
        }

        // 邮箱验证码校验（后台可开关）
        if (Setting::bool('register_email_verify', false)) {
            $code = (string) Request::input('email_code', '');
            if ($code === '' || !CodeService::verify('mail', $data['email'], 'register', $code)) {
                Session::put('_register_error', '邮箱验证码错误或已过期');
                redirect('/register');
            }
        }

        $invite = (string) Setting::get('register_invite', '');
        if ($invite !== '' && !hash_equals($invite, $data['invite'])) {
            Session::put('_register_error', '邀请码不正确');
            redirect('/register');
        }

        $groupId = Setting::int('default_group_id', 0);
        if ($groupId <= 0 || Group::find($groupId) === null) {
            $default = Group::defaultGroup();
            $groupId = $default === null ? 1 : (int) $default['id'];
        }

        $userData = [
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'group_id' => $groupId,
            'is_admin' => User::countAll() === 1 ? 0 : 0,
            'status'   => 1,
        ];
        // 只有短信验证码模式才写 phone，避免无关路径触碰新列
        if ($phone !== '') {
            $userData['phone'] = $phone;
        }
        $userId = User::create($userData);

        // 新用户注册自动绑定后台设置的默认套餐：
        // 拿到套餐对应的用户组和容量，前台展示的权益买前买后一致。
        self::applyDefaultPackage($userId);

        $user = User::find($userId);
        if ($user !== null) {
            Auth::login($user);
            Log::record($userId, 'register');
        }
        redirect('/dashboard');
    }

    /**
     * 新用户注册后自动绑定后台指定的默认套餐。
     * 套餐不存在、已下架、或后台把默认套餐设为 0/留空时，什么都不做，
     * 用户保持注册时按 default_group_id 分的组，不受影响。
     *
     * 供本站注册和第三方登录新用户两个入口共用。
     */
    public static function applyDefaultPackage(int $userId): void
    {
        $packageId = Setting::int('default_package_id', 0);
        if ($packageId <= 0) {
            return;
        }
        $package = Package::find($packageId);
        if ($package === null || (int) $package['status'] !== 1) {
            return;
        }
        $user = User::find($userId);
        if ($user === null) {
            return;
        }
        PaymentService::grantPackage($user, $package, false);
    }

    public function logout(): void
    {
        $this->validateCsrf();
        $id = Auth::id();
        Auth::logout();
        if ($id !== null) {
            Log::record($id, 'logout');
        }
        redirect('/login');
    }

    private function safeRedirect(string $target): string
    {
        if ($target === '' || str_starts_with($target, '//') || !str_starts_with($target, '/')) {
            return '/';
        }
        return $target;
    }
}
