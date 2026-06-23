<?php
namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Application;

/**
 * 角色权限中间件
 * 验证用户角色是否在允许列表中
 */
class RoleMiddleware
{
    private string $role;

    public function __construct(string $role)
    {
        $this->role = $role;
    }

    public function handle(array $request, callable $next): mixed
    {
        $userRole = $_SERVER['HTTP_X_USER_ROLE'] ?? '';

        if ($this->role === 'admin') {
            if ($userRole !== 'admin') {
                Application::jsonResponse(403, '仅超级管理员可操作', null, 403);
            }
        } elseif ($this->role === 'admin_or_project_admin') {
            if (!in_array($userRole, ['admin', 'project_admin'])) {
                Application::jsonResponse(403, '无权限操作', null, 403);
            }
        } elseif ($this->role === 'agent') {
            if ($userRole !== 'agent') {
                Application::jsonResponse(403, '仅代理可操作', null, 403);
            }
        }

        return $next($request);
    }
}