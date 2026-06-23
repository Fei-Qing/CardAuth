<?php
namespace App\Core;

/**
 * 应用程序核心类
 * 负责请求分发、路由匹配、中间件执行
 */
class Application
{
    private Router $router;
    private array $middleware = [];

    public function __construct()
    {
        $this->router = new Router();
        $this->loadRoutes();
    }

    /**
     * 加载路由文件
     */
    private function loadRoutes(): void
    {
        $this->router = require ROUTES_PATH . '/api.php';
    }

    /**
     * 运行应用
     */
    public function run(): void
    {
        // 跨域处理
        $this->handleCors();

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $method  = $_SERVER['REQUEST_METHOD'];
        $uri     = $this->parseUri();

        // 匹配路由
        $route = $this->router->match($method, $uri);

        if (!$route) {
            $this->jsonResponse(404, '接口不存在');
            return;
        }

        // 执行路由中间件和处理器
        $this->dispatch($route);
    }

    /**
     * 解析请求URI
     */
    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = trim($uri, '/');
        // 移除入口文件前缀（仅当 SCRIPT_NAME 是真实脚本文件时，非 PHP 内置服务器转发的 URI）
        $scriptName = trim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        $scriptBasename = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
        if ($scriptName && $scriptBasename && str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', $scriptBasename) && strpos($uri, $scriptName) === 0) {
            $uri = substr($uri, strlen($scriptName));
        }
        $uri = trim($uri, '/');
        return $uri ?: '/';
    }

    /**
     * 分发路由
     */
    private function dispatch(array $route): void
    {
        $middleware = $route['middleware'] ?? [];
        $handler    = $route['handler'];
        $params     = array_values($route['params'] ?? []);

        // 构建中间件链
        $pipeline = array_reduce(
            array_reverse($middleware),
            function ($next, $m) {
                return function ($request) use ($m, $next) {
                    $instance = is_object($m) ? $m : new $m();
                    return $instance->handle($request, $next);
                };
            },
            function ($request) use ($handler, $params) {
                if (is_array($handler) && count($handler) === 2) {
                    [$class, $method] = $handler;
                    $controller = new $class();
                    return call_user_func_array([$controller, $method], $params);
                }
                if (is_callable($handler)) {
                    return call_user_func_array($handler, $params);
                }
                return $this->jsonResponse(500, '无效的路由处理器');
            }
        );

        $pipeline([]);
    }

    /**
     * 处理跨域请求
     */
    private function handleCors(): void
    {
        $origins = config('app.allowed_origins', ['*']);
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '*';

        if (in_array('*', $origins) || in_array($origin, $origins)) {
            header('Access-Control-Allow-Origin: ' . ($origins === ['*'] ? '*' : $origin));
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Api-Key');
        header('Access-Control-Max-Age: 86400');
        header('Access-Control-Allow-Credentials: true');
    }

    /**
     * JSON响应
     */
    public static function jsonResponse(int $code, string $message, mixed $data = null, int $httpCode = 200): never
    {
        http_response_code($httpCode);
        echo json_encode([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}