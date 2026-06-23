<?php
namespace App\Core;

/**
 * 简易路由器
 * 支持GET/POST/PUT/DELETE/PATCH方法，支持中间件
 */
class Router
{
    private array $routes = [];
    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    /**
     * 注册路由
     */
    public function add(string $method, string $uri, mixed $handler, array $middleware = []): void
    {
        $uri = trim($this->groupPrefix . '/' . trim($uri, '/'), '/');
        $uri = $uri ?: '/';
        $this->routes[] = [
            'method'     => strtoupper($method),
            'uri'        => $uri,
            'pattern'    => $this->compilePattern($uri),
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    /**
     * 路由分组
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $prevPrefix     = $this->groupPrefix;
        $prevMiddleware = $this->groupMiddleware;

        $this->groupPrefix     = $prevPrefix . '/' . trim($prefix, '/');
        $this->groupMiddleware = array_merge($prevMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix     = $prevPrefix;
        $this->groupMiddleware = $prevMiddleware;
    }

    /**
     * 编译路由模式
     */
    private function compilePattern(string $uri): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $uri);
        return '#^' . $pattern . '$#';
    }

    /**
     * 匹配路由
     */
    public function match(string $method, string $uri): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) continue;

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return [
                    'handler'    => $route['handler'],
                    'middleware' => $route['middleware'],
                    'params'     => $params,
                ];
            }
        }
        return null;
    }

    // 便捷方法
    public function get(string $uri, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $uri, $handler, $middleware);
    }
    public function post(string $uri, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $uri, $handler, $middleware);
    }
    public function put(string $uri, mixed $handler, array $middleware = []): void
    {
        $this->add('PUT', $uri, $handler, $middleware);
    }
    public function delete(string $uri, mixed $handler, array $middleware = []): void
    {
        $this->add('DELETE', $uri, $handler, $middleware);
    }
    public function patch(string $uri, mixed $handler, array $middleware = []): void
    {
        $this->add('PATCH', $uri, $handler, $middleware);
    }
}