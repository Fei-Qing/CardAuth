<?php
namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Application;

/**
 * API密钥验证中间件
 * 用于客户端授权验证接口
 */
class ApiKeyMiddleware extends Middleware
{
    public function handle(array $request, callable $next): mixed
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');

        if (empty($apiKey)) {
            Application::jsonResponse(401, '缺少API密钥', null, 401);
        }

        $db = \App\Core\Database::getInstance();
        $project = $db->fetch(
            "SELECT id, api_key FROM {$db->table('projects')} WHERE api_key = ? AND status = 1 AND deleted_at IS NULL",
            [$apiKey]
        );

        if (!$project) {
            Application::jsonResponse(403, 'API密钥无效或项目已禁用', null, 403);
        }

        $_SERVER['HTTP_X_PROJECT_ID'] = $project['id'];

        return $next($request);
    }
}