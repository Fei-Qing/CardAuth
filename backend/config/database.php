<?php
/**
 * 数据库配置文件
 * 支持多环境配置，通过 .env 文件管理敏感信息
 */
return [
    'driver'    => 'mysql',
    'host'      => env('DB_HOST', '127.0.0.1'),
    'port'      => env('DB_PORT', '3306'),
    'database'  => env('DB_NAME', 'card_auth'),
    'username'  => env('DB_USER', 'root'),
    'password'  => env('DB_PASS', ''),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => env('DB_PREFIX', 'ca_'),
    'strict'    => true,
    'engine'    => 'InnoDB',
];