<?php
namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Application;

/**
 * 频率限制中间件
 * 基于IP的简单频率控制
 */
class RateLimitMiddleware extends Middleware
{
    private int $maxRequests;
    private int $windowSeconds;
    private string $prefix;

    public function __construct(int $maxRequests = 60, int $windowSeconds = 60, string $prefix = 'api')
    {
        $this->maxRequests   = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->prefix        = $prefix;
    }

    public function handle(array $request, callable $next): mixed
    {
        $ip     = md5(clientIp());
        $key    = "rate_limit:{$this->prefix}:{$ip}";
        $now    = time();
        $window = $now - $this->windowSeconds;

        $cacheDir = STORAGE_PATH . '/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheFile = $cacheDir . '/rate_limit.json';
        $data = [];
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true) ?: [];
        }

        // 清理过期记录
        if (!isset($data[$key])) {
            $data[$key] = [];
        }
        $data[$key] = array_filter($data[$key], fn($t) => $t > $window);

        if (count($data[$key]) >= $this->maxRequests) {
            Application::jsonResponse(429, '请求过于频繁，请稍后再试', null, 429);
        }

        $data[$key][] = $now;
        file_put_contents($cacheFile, json_encode($data), LOCK_EX);

        return $next($request);
    }
}