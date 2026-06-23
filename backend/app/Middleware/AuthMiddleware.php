<?php
namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Application;
use App\Core\Database;
use App\Services\JwtService;

/**
 * JWT认证中间件
 * 验证Token并从数据库刷新用户信息，确保权限变更实时生效
 */
class AuthMiddleware extends Middleware
{
    public function handle(array $request, callable $next): mixed
    {
        // 优先从 Authorization 请求头获取 Token
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token  = '';

        if (!empty($header) && str_starts_with($header, 'Bearer ')) {
            $token = substr($header, 7);
        }

        // 兼容导出/下载场景：从 URL 查询参数获取 Token
        if (empty($token)) {
            $token = $_GET['token'] ?? '';
        }

        if (empty($token)) {
            Application::jsonResponse(401, '未登录或Token已过期', null, 401);
        }

        $jwtService = new JwtService();
        $tokenUser = $jwtService->verifyToken($token);

        if (!$tokenUser) {
            Application::jsonResponse(401, 'Token无效或已过期，请重新登录', null, 401);
        }

        // 每次请求从数据库刷新用户状态，确保权限变更实时生效
        $db = Database::getInstance();
        $user = $db->fetch(
            "SELECT id, username, nickname, role, email, phone, parent_id, project_ids, status, deleted_at 
             FROM {$db->table('users')} 
             WHERE id = ? AND deleted_at IS NULL",
            [$tokenUser['id']]
        );

        if (!$user) {
            Application::jsonResponse(401, '用户不存在或已被删除', null, 401);
        }

        if ((int) $user['status'] !== 1) {
            Application::jsonResponse(401, '账号已被禁用，请联系管理员', null, 401);
        }

        // 将用户信息注入请求上下文
        $_SERVER['HTTP_X_USER_ID']          = $user['id'];
        $_SERVER['HTTP_X_USER_ROLE']        = $user['role'];
        $_SERVER['HTTP_X_USER_NAME']        = $user['username'];
        $_SERVER['HTTP_X_USER_NICKNAME']    = $user['nickname'];
        $_SERVER['HTTP_X_USER_PARENT']      = $user['parent_id'];
        $_SERVER['HTTP_X_USER_PROJECT_IDS'] = $user['project_ids'] ?? '[]';

        return $next($request);
    }
}
