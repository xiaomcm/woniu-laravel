<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 *
 * 接口路由。
 *
 *既有的接口既支持 Bearer Token，也允许已登录的会话直接调用，
 * 所以这一组路由同样挂着会话中间件（在 bootstrap/app.php 里给 api 组补上）。
 *
 * 路由总数：102 条（含用户、图片、相册、令牌、套餐、订单、工单、反馈、
 * 举报、日志、存储、分组、驱动、社交登录、站点、统计、管理端等）。
 *
 * 约定：带 {id} 的约束为 [0-9]+；带 {no} 的约束为 [A-Za-z0-9_\-]+；
 * 静态路由（如 /orders/stat、/packages/active、/storages/default）必须写在
 * 同前缀的 {param} 路由之前，否则会被前者先匹配。
 */

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

/*
 * 账户 / 个人中心
 */
Route::get('/me', [ApiController::class, 'me']);
Route::get('/me/quota', [ApiController::class, 'userQuota']);
Route::post('/me/password', [ApiController::class, 'changePassword']);
Route::post('/me/email', [ApiController::class, 'changeEmail']);
Route::post('/me/avatar', [ApiController::class, 'userAvatar']);
Route::get('/me/logs', [ApiController::class, 'userLogs']);
Route::get('/me/oauth', [ApiController::class, 'userOauth']);
Route::delete('/me/oauth/{id}', [ApiController::class, 'unbindOauth'])->where('id', '[0-9]+');

Route::get('/profile', [ApiController::class, 'profile']);
Route::post('/profile', [ApiController::class, 'updateProfile']);

/*
 * 上传
 */
Route::post('/upload', [ApiController::class, 'upload']);

/*
 * 图片
 */
Route::get('/images', [ApiController::class, 'images']);
Route::get('/images/trash', [ApiController::class, 'imagesTrash']);
Route::get('/images/search', [ApiController::class, 'imagesSearch']);
Route::get('/images/random', [ApiController::class, 'imagesRandom']);
Route::get('/images/stat', [ApiController::class, 'imagesStat']);
Route::post('/images/batch-delete', [ApiController::class, 'imagesBatchDelete']);
Route::post('/images/batch-move', [ApiController::class, 'imagesBatchMove']);
Route::get('/images/{id}', [ApiController::class, 'image'])->where('id', '[0-9]+');
Route::post('/images/{id}', [ApiController::class, 'updateImage'])->where('id', '[0-9]+');
Route::put('/images/{id}', [ApiController::class, 'updateImage'])->where('id', '[0-9]+');
Route::post('/images/{id}/move', [ApiController::class, 'moveImage'])->where('id', '[0-9]+');
Route::post('/images/{id}/restore', [ApiController::class, 'restoreImage'])->where('id', '[0-9]+');
Route::get('/images/{id}/meta', [ApiController::class, 'imageMeta'])->where('id', '[0-9]+');
Route::get('/images/{id}/download', [ApiController::class, 'imageDownload'])->where('id', '[0-9]+');
Route::post('/images/{id}/copy', [ApiController::class, 'imageCopy'])->where('id', '[0-9]+');
Route::delete('/images/{id}', [ApiController::class, 'destroy'])->where('id', '[0-9]+');

/*
 * 相册
 */
Route::get('/albums', [ApiController::class, 'albums']);
Route::post('/albums', [ApiController::class, 'albumStore']);
Route::get('/albums/{id}', [ApiController::class, 'album'])->where('id', '[0-9]+');
Route::post('/albums/{id}', [ApiController::class, 'albumUpdate'])->where('id', '[0-9]+');
Route::put('/albums/{id}', [ApiController::class, 'albumUpdate'])->where('id', '[0-9]+');
Route::delete('/albums/{id}', [ApiController::class, 'albumDelete'])->where('id', '[0-9]+');
Route::get('/albums/{id}/images', [ApiController::class, 'albumImages'])->where('id', '[0-9]+');
Route::post('/albums/{id}/images', [ApiController::class, 'albumAddImage'])->where('id', '[0-9]+');
Route::delete('/albums/{id}/images/{imageId}', [ApiController::class, 'albumRemoveImage'])->where(['id' => '[0-9]+', 'imageId' => '[0-9]+']);
Route::post('/albums/{id}/cover', [ApiController::class, 'albumCover'])->where('id', '[0-9]+');
Route::get('/albums/share/{key}', [ApiController::class, 'albumShare'])->where('key', '[A-Za-z0-9]+');
Route::post('/albums/{id}/password', [ApiController::class, 'albumPassword'])->where('id', '[0-9]+');

/*
 * 令牌 / API Key
 */
Route::get('/tokens', [ApiController::class, 'tokens']);
Route::post('/tokens', [ApiController::class, 'tokenStore']);
Route::get('/tokens/{id}', [ApiController::class, 'tokenShow'])->where('id', '[0-9]+');
Route::post('/tokens/{id}/rename', [ApiController::class, 'tokenRename'])->where('id', '[0-9]+');
Route::post('/tokens/{id}/revoke', [ApiController::class, 'tokenRevoke'])->where('id', '[0-9]+');
Route::post('/tokens/revoke-all', [ApiController::class, 'tokensRevokeAll']);
Route::delete('/tokens/{id}', [ApiController::class, 'tokenDelete'])->where('id', '[0-9]+');

/*
 * 套餐 / 订单 / 优惠券
 */
Route::get('/packages', [ApiController::class, 'packages']);
Route::get('/packages/active', [ApiController::class, 'packagesActive']);
Route::get('/packages/{id}', [ApiController::class, 'package'])->where('id', '[0-9]+');
Route::get('/packages/{id}/features', [ApiController::class, 'packageFeatures'])->where('id', '[0-9]+');
Route::post('/coupon/check', [ApiController::class, 'couponCheck']);
Route::get('/orders', [ApiController::class, 'orders']);
Route::post('/orders', [ApiController::class, 'orderStore']);
Route::get('/orders/stat', [ApiController::class, 'ordersStat']);
Route::get('/orders/{no}', [ApiController::class, 'order'])->where('no', '[A-Za-z0-9_\-]+');
Route::post('/orders/{no}/cancel', [ApiController::class, 'orderCancel'])->where('no', '[A-Za-z0-9_\-]+');
Route::get('/orders/{no}/pay', [ApiController::class, 'orderPayGet'])->where('no', '[A-Za-z0-9_\-]+');
Route::post('/orders/{no}/pay', [ApiController::class, 'orderPayPost'])->where('no', '[A-Za-z0-9_\-]+');

/*
 * 公告 / 单页
 */
Route::get('/notices', [ApiController::class, 'notices']);
Route::get('/notices/{id}', [ApiController::class, 'notice'])->where('id', '[0-9]+');
Route::get('/pages', [ApiController::class, 'pages']);
Route::get('/pages/{slug}', [ApiController::class, 'page'])->where('slug', '[A-Za-z0-9_\-]+');

/*
 * 工单
 */
Route::get('/tickets', [ApiController::class, 'tickets']);
Route::post('/tickets', [ApiController::class, 'ticketStore']);
Route::get('/tickets/{id}', [ApiController::class, 'ticket'])->where('id', '[0-9]+');
Route::post('/tickets/{id}/replies', [ApiController::class, 'ticketReply'])->where('id', '[0-9]+');
Route::get('/tickets/{id}/replies', [ApiController::class, 'ticketReplies'])->where('id', '[0-9]+');
Route::post('/tickets/{id}/close', [ApiController::class, 'ticketClose'])->where('id', '[0-9]+');
Route::post('/tickets/{id}/reopen', [ApiController::class, 'ticketReopen'])->where('id', '[0-9]+');

/*
 * 反馈
 */
Route::get('/feedback', [ApiController::class, 'feedbackList']);
Route::post('/feedback', [ApiController::class, 'feedbackSubmit']);
Route::get('/feedback/{id}', [ApiController::class, 'feedbackShow'])->where('id', '[0-9]+');

/*
 * 举报
 */
Route::get('/reports', [ApiController::class, 'reportsList']);
Route::post('/reports', [ApiController::class, 'reportSubmit']);
Route::get('/reports/{id}', [ApiController::class, 'reportShow'])->where('id', '[0-9]+');

/*
 * 日志（管理端）
 */
Route::get('/logs', [ApiController::class, 'logsList']);
Route::get('/logs/{id}', [ApiController::class, 'logShow'])->where('id', '[0-9]+');

/*
 * 存储 / 分组 / 驱动
 */
Route::get('/storages', [ApiController::class, 'storages']);
Route::get('/storages/default', [ApiController::class, 'storagesDefault']);
Route::get('/storages/{id}', [ApiController::class, 'storageShow'])->where('id', '[0-9]+');
Route::post('/storages/{id}/test', [ApiController::class, 'storageTest'])->where('id', '[0-9]+');
Route::get('/groups', [ApiController::class, 'groupList']);
Route::get('/groups/{id}', [ApiController::class, 'groupShow'])->where('id', '[0-9]+');
Route::get('/drivers', [ApiController::class, 'drivers']);
Route::get('/drivers/{id}', [ApiController::class, 'driverShow'])->where('id', '[0-9]+');
Route::get('/regions', [ApiController::class, 'regions']);

/*
 * 社交登录 / OAuth
 */
Route::get('/socials', [ApiController::class, 'socials']);
Route::get('/oauth/{id}/url', [ApiController::class, 'oauthUrl'])->where('id', '[0-9]+');
Route::post('/oauth/{id}/bind', [ApiController::class, 'oauthBind'])->where('id', '[0-9]+');
Route::post('/oauth/{id}/unbind', [ApiController::class, 'oauthUnbind'])->where('id', '[0-9]+');

/*
 * 站点 / 配置 / 健康检查
 */
Route::get('/site/config', [ApiController::class, 'siteConfig']);
Route::get('/site/info', [ApiController::class, 'siteInfo']);
Route::get('/health', [ApiController::class, 'health']);
Route::get('/captcha/status', [ApiController::class, 'captchaStatus']);

/*
 * 统计
 */
Route::get('/stats', [ApiController::class, 'stats']);
Route::get('/stats/images', [ApiController::class, 'statsImages']);
Route::get('/stats/albums', [ApiController::class, 'statsAlbums']);
Route::get('/stats/storage', [ApiController::class, 'statsStorage']);

/*
 * 管理端聚合
 */
Route::get('/admin/stats', [ApiController::class, 'adminStats']);
Route::get('/admin/images', [ApiController::class, 'adminImages']);
Route::get('/admin/users', [ApiController::class, 'adminUsers']);
Route::get('/admin/orders', [ApiController::class, 'adminOrders']);
