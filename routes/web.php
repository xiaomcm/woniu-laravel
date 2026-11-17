<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 *
 * 前台路由。
 *
 * 参数规则与既有的自写路由保持一致：
 *   {id:num}  → [0-9]+
 *   {key:slug}→ [A-Za-z0-9_-]+
 *   {path:any}→ .+
 * 路由的注册顺序也照旧 ——原先是自上而下第一条命中即用，Laravel 同样按注册顺序匹配。
 */

use App\Http\Controllers\Admin\ContentController as AdminContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DriverController as AdminDriverController;
use App\Http\Controllers\Admin\GroupController as AdminGroupController;
use App\Http\Controllers\Admin\ImageController as AdminImageController;
use App\Http\Controllers\Admin\LogController as AdminLogController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\ShopController as AdminShopController;
use App\Http\Controllers\Admin\StorageController as AdminStorageController;
use App\Http\Controllers\Admin\SupportController as AdminSupportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaptchaController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\OauthController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 安装向导
|--------------------------------------------------------------------------
|
| 已安装时这个地址会被中间件引到「已锁定」页（见 EnsureInstalled）。
|
*/

Route::match(['get', 'post'], '/install', [\App\Http\Controllers\InstallController::class, 'index'])
    ->name('install');

/*
|--------------------------------------------------------------------------
| 前台
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index']);
// 控制台独立地址：/ 专属游客，登录用户访问 / 会被 302 弹到这里
Route::get('/dashboard', [HomeController::class, 'dashboard']);
Route::get('/login', [AuthController::class, 'loginForm']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'registerForm']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/register/code', [AuthController::class, 'sendRegisterCode']);
// 注册验证码：本地图形验证码出图、云厂商短信验证码下发
Route::get('/captcha/image', [CaptchaController::class, 'image']);
Route::post('/captcha/sms', [CaptchaController::class, 'sendSms']);
Route::post('/logout', [AuthController::class, 'logout']);

// 找回密码（邮箱验证码）
Route::get('/forgot', [AuthController::class, 'forgotForm']);
Route::post('/forgot/send', [AuthController::class, 'forgotSend']);
Route::get('/forgot/reset', [AuthController::class, 'forgotResetForm']);
Route::post('/forgot/reset', [AuthController::class, 'forgotReset']);

Route::get('/upload', [UploadController::class, 'index']);
Route::post('/upload', [UploadController::class, 'store']);

Route::get('/explore', [ExploreController::class, 'index']);
Route::get('/u/{id}', [ExploreController::class, 'user'])->where('id', '[0-9]+');
Route::get('/i/{key}', [ImageController::class, 'detail'])->where('key', '[A-Za-z0-9_\-]+');
Route::post('/i/{key}', [ImageController::class, 'unlockShare'])->where('key', '[A-Za-z0-9_\-]+');

Route::get('/images', [ImageController::class, 'index']);
Route::post('/images/download', [ImageController::class, 'download']);
Route::post('/images/delete', [ImageController::class, 'delete']);
Route::post('/images/restore', [ImageController::class, 'restore']);
Route::post('/images/destroy', [ImageController::class, 'destroy']);
Route::post('/images/move', [ImageController::class, 'move']);
Route::post('/images/{id}', [ImageController::class, 'update'])->where('id', '[0-9]+');
Route::get('/images/{id}/thumb', [ImageController::class, 'ownThumb'])->where('id', '[0-9]+');
Route::post('/images/{id}/share', [ImageController::class, 'updateShare'])->where('id', '[0-9]+');

Route::get('/albums', [AlbumController::class, 'index']);
Route::post('/albums', [AlbumController::class, 'store']);
Route::get('/albums/{id}', [AlbumController::class, 'show'])->where('id', '[0-9]+');
Route::post('/albums/{id}', [AlbumController::class, 'update'])->where('id', '[0-9]+');
Route::post('/albums/{id}/delete', [AlbumController::class, 'delete'])->where('id', '[0-9]+');
Route::get('/s/{key}', [AlbumController::class, 'share'])->where('key', '[A-Za-z0-9_\-]+');
Route::post('/s/{key}', [AlbumController::class, 'unlockShare'])->where('key', '[A-Za-z0-9_\-]+');

Route::get('/settings', [SettingsController::class, 'index']);
Route::post('/settings/profile', [SettingsController::class, 'updateProfile']);
Route::post('/settings/password', [SettingsController::class, 'updatePassword']);
Route::post('/settings/api-enabled', [SettingsController::class, 'updateApiEnabled']);
Route::post('/settings/tokens', [SettingsController::class, 'createToken']);
Route::post('/settings/tokens/{id}/delete', [SettingsController::class, 'deleteToken'])->where('id', '[0-9]+');

/*
|--------------------------------------------------------------------------
| 后台
|--------------------------------------------------------------------------
*/

Route::get('/admin', [DashboardController::class, 'index']);
Route::get('/admin/users', [AdminUserController::class, 'index']);
Route::post('/admin/users', [AdminUserController::class, 'store']);
Route::post('/admin/users/{id}', [AdminUserController::class, 'update'])->where('id', '[0-9]+');
Route::post('/admin/users/{id}/delete', [AdminUserController::class, 'delete'])->where('id', '[0-9]+');

Route::get('/admin/groups', [AdminGroupController::class, 'index']);
Route::post('/admin/groups', [AdminGroupController::class, 'store']);
Route::post('/admin/groups/{id}', [AdminGroupController::class, 'update'])->where('id', '[0-9]+');
Route::post('/admin/groups/{id}/delete', [AdminGroupController::class, 'delete'])->where('id', '[0-9]+');

Route::get('/admin/storages', [AdminStorageController::class, 'index']);
Route::post('/admin/storages', [AdminStorageController::class, 'store']);
Route::post('/admin/storages/test', [AdminStorageController::class, 'test']);
Route::post('/admin/storages/{id}', [AdminStorageController::class, 'update'])->where('id', '[0-9]+');
Route::post('/admin/storages/{id}/delete', [AdminStorageController::class, 'delete'])->where('id', '[0-9]+');

Route::get('/admin/images', [AdminImageController::class, 'index']);
Route::get('/admin/images/{id}/preview', [AdminImageController::class, 'preview'])->where('id', '[0-9]+');
Route::post('/admin/images/audit', [AdminImageController::class, 'audit']);
Route::post('/admin/images/delete', [AdminImageController::class, 'delete']);

Route::get('/admin/settings', [AdminSettingController::class, 'index']);
Route::post('/admin/settings', [AdminSettingController::class, 'save']);
Route::post('/admin/settings/mail-test', [AdminSettingController::class, 'mailTest']);
Route::post('/admin/settings/check-update', [AdminSettingController::class, 'checkUpdate']);

Route::get('/admin/logs', [AdminLogController::class, 'index']);
Route::post('/admin/logs/delete', [AdminLogController::class, 'batchDestroy']);
Route::post('/admin/logs/clear', [AdminLogController::class, 'clear']);
Route::post('/admin/logs/{id}/delete', [AdminLogController::class, 'destroy'])->where('id', '[0-9]+');
Route::get('/admin/logs/files/view', [AdminLogController::class, 'fileView']);
Route::post('/admin/logs/files/delete', [AdminLogController::class, 'fileDelete']);
Route::post('/admin/logs/files/clear', [AdminLogController::class, 'fileClear']);

Route::get('/admin/drivers', [AdminDriverController::class, 'index']);
Route::get('/admin/drivers/create', [AdminDriverController::class, 'create']);
Route::post('/admin/drivers', [AdminDriverController::class, 'store']);
Route::post('/admin/drivers/test', [AdminDriverController::class, 'test']);
Route::get('/admin/drivers/{id}/edit', [AdminDriverController::class, 'edit'])->where('id', '[0-9]+');
Route::post('/admin/drivers/{id}', [AdminDriverController::class, 'update'])->where('id', '[0-9]+');
Route::post('/admin/drivers/{id}/delete', [AdminDriverController::class, 'delete'])->where('id', '[0-9]+');

Route::get('/admin/shop/packages', [AdminShopController::class, 'packages']);
Route::post('/admin/shop/packages', [AdminShopController::class, 'packageStore']);
Route::post('/admin/shop/packages/{id}', [AdminShopController::class, 'packageUpdate'])->where('id', '[0-9]+');
Route::post('/admin/shop/packages/{id}/delete', [AdminShopController::class, 'packageDelete'])->where('id', '[0-9]+');
Route::get('/admin/shop/orders', [AdminShopController::class, 'orders']);
Route::post('/admin/shop/orders/{id}/refund', [AdminShopController::class, 'orderRefund'])->where('id', '[0-9]+');
Route::get('/admin/shop/coupons', [AdminShopController::class, 'coupons']);
Route::post('/admin/shop/coupons', [AdminShopController::class, 'couponStore']);
Route::post('/admin/shop/coupons/{id}', [AdminShopController::class, 'couponUpdate'])->where('id', '[0-9]+');
Route::post('/admin/shop/coupons/{id}/delete', [AdminShopController::class, 'couponDelete'])->where('id', '[0-9]+');

Route::get('/admin/support/tickets', [AdminSupportController::class, 'tickets']);
Route::get('/admin/support/tickets/{id}', [AdminSupportController::class, 'ticketShow'])->where('id', '[0-9]+');
Route::post('/admin/support/tickets/{id}/reply', [AdminSupportController::class, 'ticketReply'])->where('id', '[0-9]+');
Route::post('/admin/support/tickets/{id}/close', [AdminSupportController::class, 'ticketClose'])->where('id', '[0-9]+');
Route::post('/admin/support/tickets/{id}/delete', [AdminSupportController::class, 'ticketDelete'])->where('id', '[0-9]+');
Route::get('/admin/support/reports', [AdminSupportController::class, 'reports']);
Route::post('/admin/support/reports', [AdminSupportController::class, 'reportRedirect']);
Route::post('/admin/support/reports/{id}/delete', [AdminSupportController::class, 'reportDelete'])->where('id', '[0-9]+');
Route::post('/admin/support/reports/{id}', [AdminSupportController::class, 'reportHandle'])->where('id', '[0-9]+');
Route::get('/admin/support/feedbacks', [AdminSupportController::class, 'feedbacks']);
Route::post('/admin/support/feedbacks/{id}', [AdminSupportController::class, 'feedbackReply'])->where('id', '[0-9]+');

Route::get('/admin/content/pages', [AdminContentController::class, 'pages']);
Route::post('/admin/content/pages', [AdminContentController::class, 'pageStore']);
Route::post('/admin/content/pages/{id}', [AdminContentController::class, 'pageUpdate'])->where('id', '[0-9]+');
Route::post('/admin/content/pages/{id}/delete', [AdminContentController::class, 'pageDelete'])->where('id', '[0-9]+');
Route::get('/admin/content/notices', [AdminContentController::class, 'notices']);
Route::post('/admin/content/notices', [AdminContentController::class, 'noticeStore']);
Route::post('/admin/content/notices/{id}', [AdminContentController::class, 'noticeUpdate'])->where('id', '[0-9]+');
Route::post('/admin/content/notices/{id}/delete', [AdminContentController::class, 'noticeDelete'])->where('id', '[0-9]+');

/*
|--------------------------------------------------------------------------
| 商城 / 支付 / 工单 / 第三方登录
|--------------------------------------------------------------------------
*/

Route::get('/shop', [ShopController::class, 'index']);
Route::get('/shop/checkout/{id}', [ShopController::class, 'checkout'])->where('id', '[0-9]+');
Route::post('/shop/quote', [ShopController::class, 'quote']);
Route::post('/shop/pay', [ShopController::class, 'pay']);
Route::get('/shop/orders', [ShopController::class, 'orders']);
Route::get('/shop/orders/{no}', [ShopController::class, 'orderShow'])->where('no', '[A-Za-z0-9_\-]+');
Route::get('/shop/status/{no}', [ShopController::class, 'status'])->where('no', '[A-Za-z0-9_\-]+');
Route::post('/pay/notify/{id}', [PaymentController::class, 'notify'])->where('id', '[0-9]+');
Route::get('/pay/notify/{id}', [PaymentController::class, 'notify'])->where('id', '[0-9]+');
Route::get('/pay/return', [PaymentController::class, 'back']);

Route::get('/support/tickets', [SupportController::class, 'tickets']);
Route::get('/support/tickets/new', [SupportController::class, 'ticketCreate']);
Route::post('/support/tickets', [SupportController::class, 'ticketStore']);
Route::get('/support/tickets/{id}', [SupportController::class, 'ticketShow'])->where('id', '[0-9]+');
Route::post('/support/tickets/{id}/reply', [SupportController::class, 'ticketReply'])->where('id', '[0-9]+');
Route::post('/support/tickets/{id}/delete', [SupportController::class, 'ticketDelete'])->where('id', '[0-9]+');
Route::get('/feedback', [SupportController::class, 'feedback']);
Route::post('/feedback', [SupportController::class, 'feedbackStore']);
Route::post('/report', [SupportController::class, 'report']);

Route::get('/oauth/{id}', [OauthController::class, 'redirect'])->where('id', '[0-9]+');
Route::get('/oauth/callback', [OauthController::class, 'callback']);

Route::get('/notices', [PageController::class, 'notices']);
Route::get('/p/{slug}', [PageController::class, 'show'])->where('slug', '[A-Za-z0-9_\-]+');

// API 用法文档页。路径用 api-docs 而不是 api/docs，避免和 /api/v1 接口前缀混淆
Route::get('/api-docs', [ApiDocsController::class, 'index']);

/*
|--------------------------------------------------------------------------
| 静态资源与图片直链
|--------------------------------------------------------------------------
*/

Route::get('/assets/{path}', static function (string $path): void {
    if (strpos($path, '..') !== false) {
        abort(404);
    }
    $file = BASE_PATH . '/public/assets/' . $path;
    if (!is_file($file)) {
        abort(404);
    }
    \App\Core\Response::file($file);
})->where('path', '.+');

Route::get('/uploads/{path}', [ImageController::class, 'serve'])->where('path', '.+');
Route::get('/thumbs/{path}', [ImageController::class, 'thumb'])->where('path', '.+');
// 广场封面：固定 1280×860，服务端裁好再发，卡片高度不会跳
Route::get('/covers/{path}', [ImageController::class, 'cover'])->where('path', '.+');
// 下架/拒绝/删除图片的占位图：纯白块，比例跟原图一致，避免列表高度错位
Route::get('/blank/{spec}', [ImageController::class, 'blank'])->where('spec', '.+');

// 图片直链短地址：/2025/09/07/xxx.jpg，不带 uploads 前缀。
// 老地址 /uploads/2025/... 继续可用，已经发出去的链接不会失效。
// 只匹配「4位年/2位月/2位日/文件名」这个形状，不会吃掉别的路由，所以放最后一条。
Route::get('/{path}', [ImageController::class, 'serve'])
    ->where('path', '\d{4}/\d{2}/\d{2}/[A-Za-z0-9._\-]+');
