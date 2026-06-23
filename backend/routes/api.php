<?php
/**
 * API路由配置
 * 所有路由在此文件中定义
 */

use App\Controllers\AuthController;
use App\Controllers\ProjectController;
use App\Controllers\CardController;
use App\Controllers\AgentController;
use App\Controllers\OrderController;
use App\Controllers\PublicController;
use App\Controllers\DashboardController;
use App\Controllers\SystemController;
use App\Controllers\AuthorizationController;
use App\Controllers\CouponController;
use App\Controllers\BlacklistController;
use App\Controllers\NotificationController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\ApiKeyMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\BlacklistMiddleware;

// $router由Application创建并传入
// 使用匿名函数捕获$router

/** @var \App\Core\Router $router */
$router = new \App\Core\Router();

// ============================================
// 公开接口 (无需认证)
// ============================================
$router->group('api/public', function ($router) {
    // 购买流程
    $router->get('projects', [PublicController::class, 'projects']);
    $router->get('projects/{id}/card-types', [PublicController::class, 'cardTypes']);
    $router->post('orders', [PublicController::class, 'createOrder']);
    $router->get('orders/query', [PublicController::class, 'queryOrder']);

    // 卡密查询
    $router->get('cards/query', [PublicController::class, 'queryCard']);

    // 支付回调
    $router->get('payment/notify', [PublicController::class, 'paymentNotify']);
    $router->post('payment/notify', [PublicController::class, 'paymentNotify']);
    $router->post('payment/complete', [PublicController::class, 'paymentComplete']);

    // 授权查询与在线授权（公开接口）
    $router->get('authorizations/query', [AuthorizationController::class, 'queryByBot']);
    $router->get('authorizations/query-by-contact', [AuthorizationController::class, 'queryByContact']);
    $router->post('authorizations/verify', [AuthorizationController::class, 'verify']);
    $router->post('authorizations/activate', [AuthorizationController::class, 'publicActivate']);

    // 优惠码验证
    $router->post('coupons/validate', [CouponController::class, 'validate']);
}, [new RateLimitMiddleware(30, 60, 'public')]);

// SMTP 定时通知 (cron 调用，无需认证)
$router->get('api/cron/notify-expiring', [NotificationController::class, 'notifyExpiring']);
$router->post('api/cron/notify-expiring', [NotificationController::class, 'notifyExpiring']);

// 授权验证 (API Key认证)
$router->group('api/public/verify', function ($router) {
    $router->post('', [PublicController::class, 'verify']);
}, [new ApiKeyMiddleware(), new RateLimitMiddleware(60, 60, 'verify')]);

// ============================================
// 认证接口 (无需JWT)
// ============================================
$router->group('api/auth', function ($router) {
    $router->post('admin-login', [AuthController::class, 'adminLogin']);
    $router->post('agent-login', [AuthController::class, 'agentLogin']);
    $router->post('agent-register', [AuthController::class, 'agentRegister']);
    $router->post('refresh', [AuthController::class, 'refreshToken']);
}, [new RateLimitMiddleware(10, 60, 'auth')]);

// ============================================
// 需要认证的接口
// ============================================
$router->group('api', function ($router) {

    // 用户信息
    $router->get('auth/me', [AuthController::class, 'me']);
    $router->put('auth/profile', [AuthController::class, 'updateProfile']);
    $router->post('auth/change-password', [AuthController::class, 'changePassword']);
    $router->post('auth/avatar', [AuthController::class, 'uploadAvatar']);

    // Dashboard
    $router->get('dashboard', [DashboardController::class, 'index']);
    $router->get('logs', [DashboardController::class, 'logs']);
    $router->get('logs/export', [DashboardController::class, 'exportLogs']);

    // 用户管理 (Admin)
    $router->get('users', [AuthController::class, 'list']);
    $router->get('users/stats', [AuthController::class, 'stats']);
    $router->get('users/export', [AuthController::class, 'export']);
    $router->post('users', [AuthController::class, 'create']);
    $router->put('users/{id}', [AuthController::class, 'update']);
    $router->delete('users/{id}', [AuthController::class, 'delete']);
    $router->post('users/batch-delete', [AuthController::class, 'batchDelete']);

    // 项目管理
    $router->get('projects', [ProjectController::class, 'list']);
    $router->get('projects/all', [ProjectController::class, 'allProjects']);
    $router->get('projects/export', [ProjectController::class, 'export']);
    $router->get('projects/{id}', [ProjectController::class, 'detail']);
    $router->post('projects', [ProjectController::class, 'create']);
    $router->put('projects/{id}', [ProjectController::class, 'update']);
    $router->delete('projects/{id}', [ProjectController::class, 'delete']);
    $router->post('projects/{id}/regenerate-key', [ProjectController::class, 'regenerateApiKey']);

    // 卡密类型管理
    $router->get('projects/{projectId}/card-types', [ProjectController::class, 'cardTypes']);
    $router->post('projects/{projectId}/card-types', [ProjectController::class, 'createCardType']);
    $router->put('projects/{projectId}/card-types/{typeId}', [ProjectController::class, 'updateCardType']);
    $router->delete('projects/{projectId}/card-types/{typeId}', [ProjectController::class, 'deleteCardType']);

    // 商品管理 (全量)
    $router->get('products', [ProjectController::class, 'allProducts']);
    $router->get('products/export', [ProjectController::class, 'productsExport']);

    // 卡密管理
    $router->get('cards', [CardController::class, 'list']);
    $router->get('cards/stats', [CardController::class, 'stats']);
    $router->get('cards/export', [CardController::class, 'export']);
    $router->post('cards/import', [CardController::class, 'import']);
    $router->post('cards/batch-status', [CardController::class, 'batchStatus']);
    $router->post('cards/batch-delete', [CardController::class, 'batchDelete']);
    $router->get('cards/{id}', [CardController::class, 'detail']);
    $router->post('cards/generate', [CardController::class, 'generate']);
    $router->patch('cards/{id}/status', [CardController::class, 'toggleStatus']);

    // 代理与额度管理
    $router->get('agents', [AgentController::class, 'list']);
    $router->get('agents/export', [AgentController::class, 'export']);
    $router->get('agents/my-quota', [AgentController::class, 'myQuota']);
    $router->post('agents/recharge', [AgentController::class, 'recharge']);
    $router->post('agents/adjust-quota', [AgentController::class, 'adjustQuota']);
    $router->get('agents/quota-logs', [AgentController::class, 'quotaLogs']);
    $router->post('agents/authorize', [AgentController::class, 'authorizeCard']);
    $router->post('agents/{id}/reset-password', [AgentController::class, 'resetPassword']);

    // 订单管理
    $router->get('orders', [OrderController::class, 'list']);
    $router->get('orders/export', [OrderController::class, 'export']);
    $router->get('orders/{id}', [OrderController::class, 'detail']);
    $router->post('orders/{id}/complete', [OrderController::class, 'manualComplete']);

    // 系统配置
    $router->get('system/configs', [SystemController::class, 'getConfigs']);
    $router->post('system/configs', [SystemController::class, 'saveConfigs']);
    $router->post('system/test-payment', [SystemController::class, 'testPayment']);
    // SMTP 邮件
    $router->get('system/smtp-config', [NotificationController::class, 'getSmtpConfig']);
    $router->post('system/smtp-config', [NotificationController::class, 'saveSmtpConfig']);
    $router->post('system/test-smtp', [NotificationController::class, 'testSmtp']);
    $router->post('system/notify-expiring', [NotificationController::class, 'notifyExpiring']);

    // 授权管理
    $router->get('authorizations', [AuthorizationController::class, 'list']);
    $router->get('authorizations/stats', [AuthorizationController::class, 'stats']);
    $router->get('authorizations/export', [AuthorizationController::class, 'export']);
    $router->get('authorizations/{id}', [AuthorizationController::class, 'detail']);
    $router->post('authorizations', [AuthorizationController::class, 'create']);
    $router->put('authorizations/{id}', [AuthorizationController::class, 'update']);
    $router->put('authorizations/{id}/revoke', [AuthorizationController::class, 'revoke']);
    $router->delete('authorizations/{id}', [AuthorizationController::class, 'delete']);
    $router->post('authorizations/batch-delete', [AuthorizationController::class, 'batchDelete']);

    // 优惠码管理
    $router->get('coupons', [CouponController::class, 'list']);
    $router->get('coupons/export', [CouponController::class, 'export']);
    $router->post('coupons', [CouponController::class, 'create']);
    $router->put('coupons/{id}', [CouponController::class, 'update']);
    $router->delete('coupons/{id}', [CouponController::class, 'delete']);

    // 黑名单管理（仅管理员）
    $router->group('blacklists', function ($router) {
        $router->get('', [BlacklistController::class, 'list']);
        $router->post('', [BlacklistController::class, 'create']);
        $router->post('batch', [BlacklistController::class, 'batchCreate']);
        $router->put('{id}', [BlacklistController::class, 'update']);
        $router->delete('{id}', [BlacklistController::class, 'delete']);
        $router->post('batch-delete', [BlacklistController::class, 'batchDelete']);
        $router->post('import', [BlacklistController::class, 'import']);
        $router->get('export', [BlacklistController::class, 'export']);
    }, [new RoleMiddleware('admin')]);

}, [new AuthMiddleware(), new BlacklistMiddleware(), new RateLimitMiddleware(60, 60, 'api')]);


// 返回router给Application
return $router;