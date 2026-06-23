<?php
/**
 * PHP内置服务器路由器
 * 用法: php -S 0.0.0.0:8080 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// 静态文件直接返回
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    $filePath = __DIR__ . '/public' . $uri;
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        'css' => 'text/css', 'js' => 'application/javascript',
        'json' => 'application/json', 'ico' => 'image/x-icon',
    ];
    header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: public, max-age=31536000');
    readfile($filePath);
    exit;
}

// API 请求走后端入口
if (str_starts_with($uri, '/api/')) {
    require_once __DIR__ . '/public/index.php';
    exit;
}

// 非 API 请求返回前端 SPA 入口（index.html），前端 Vue Router 负责渲染路由
$indexHtml = __DIR__ . '/public/index.html';
if (file_exists($indexHtml)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($indexHtml);
    exit;
}

// 兜底：无法处理
http_response_code(404);
echo 'Not Found';
exit;
