<?php
namespace App\Core;

/**
 * 中间件基类
 */
abstract class Middleware
{
    /**
     * 处理请求
     * @param array $request 请求上下文
     * @param callable $next 下一个处理器
     * @return mixed
     */
    abstract public function handle(array $request, callable $next): mixed;
}