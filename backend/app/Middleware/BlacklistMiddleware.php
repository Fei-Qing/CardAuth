<?php
namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Application;
use App\Services\BlacklistService;

/**
 * 黑名单拦截中间件
 * 在请求处理前检查当前用户或IP是否被拉黑
 */
class BlacklistMiddleware extends Middleware
{
    public function handle(array $request, callable $next): mixed
    {
        $userId = $_SERVER['HTTP_X_USER_ID'] ?? null;
        $ip     = clientIp();

        $service = new BlacklistService();
        if ($service->isBlocked($userId, $ip)) {
            Application::jsonResponse(403, '您的账号或IP已被列入黑名单，禁止访问', null, 403);
        }

        return $next($request);
    }
}
