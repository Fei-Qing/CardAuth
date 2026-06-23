<?php
namespace App\Core;

/**
 * 控制器基类
 */
abstract class Controller
{
    /**
     * 获取请求体JSON数据
     */
    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?: [];
    }

    /**
     * 获取GET参数
     */
    protected function getQuery(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * 获取请求参数（合并GET和JSON Body）
     */
    protected function getInput(string $key, mixed $default = null): mixed
    {
        $json = $this->getJsonInput();
        return $json[$key] ?? $_GET[$key] ?? $_POST[$key] ?? $default;
    }

    /**
     * 返回成功响应
     */
    protected function success(mixed $data = null, string $message = '操作成功', int $code = 200): never
    {
        Application::jsonResponse($code, $message, $data);
    }

    /**
     * 返回错误响应
     */
    protected function error(string $message = '操作失败', int $code = 400, mixed $data = null, int $httpCode = 200): never
    {
        Application::jsonResponse($code, $message, $data, $httpCode);
    }

    /**
     * 获取当前用户ID（由Auth中间件注入）
     */
    protected function getUserId(): ?int
    {
        return $_SERVER['HTTP_X_USER_ID'] ?? null;
    }

    /**
     * 获取当前用户角色
     */
    protected function getUserRole(): string
    {
        return $_SERVER['HTTP_X_USER_ROLE'] ?? '';
    }

    /**
     * 获取当前用户信息（由Auth中间件从数据库实时刷新）
     */
    protected function getCurrentUser(): ?array
    {
        $userId = $this->getUserId();
        if (!$userId) return null;

        return [
            'id'          => (int) $userId,
            'username'    => $_SERVER['HTTP_X_USER_NAME'] ?? '',
            'nickname'    => $_SERVER['HTTP_X_USER_NICKNAME'] ?? '',
            'role'        => $_SERVER['HTTP_X_USER_ROLE'] ?? '',
            'parent_id'   => (int) ($_SERVER['HTTP_X_USER_PARENT'] ?? 0),
            'project_ids' => json_decode($_SERVER['HTTP_X_USER_PROJECT_IDS'] ?? '[]', true) ?: [],
        ];
    }
}