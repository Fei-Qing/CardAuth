<?php
/**
 * CardAuth - 授权管理系统入口文件
 * 所有HTTP请求通过此文件统一分发
 */

// 错误报告设置
$isDev = (getenv('APP_ENV') ?: 'production') === 'development';
$isDebug = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN);

error_reporting($isDev ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/error.log');

// 生产环境隐藏 PHP 版本
if (!$isDev) {
    ini_set('expose_php', '0');
    header_remove('X-Powered-By');
}

// 定义基础路径
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('ROUTES_PATH', BASE_PATH . '/routes');
define('STORAGE_PATH', BASE_PATH . '/storage');

// 自动加载
require_once BASE_PATH . '/vendor/autoload.php';

// 加载环境变量
if (file_exists(BASE_PATH . '/.env')) {
    $lines = file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// 加载helper函数
require_once APP_PATH . '/Helpers/functions.php';

// 设置时区
date_default_timezone_set(config('app.timezone', 'Asia/Shanghai'));

// 启动Session（仅用于CSRF Token，实际API认证使用JWT）
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// 处理请求
try {
    $app = new App\Core\Application();
    $app->run();
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'code'    => 500,
        'message' => $isDebug ? $e->getMessage() : '服务器内部错误',
        'data'    => null,
    ], JSON_UNESCAPED_UNICODE);
    if ($isDebug) {
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    }
}