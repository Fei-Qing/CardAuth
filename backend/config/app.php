<?php
/**
 * 应用配置文件
 */
return [
    'name'            => 'CardAuth',
    'version'         => '1.0.0',
    'debug'           => env('APP_DEBUG', false),
    'timezone'        => 'Asia/Shanghai',
    'jwt_secret'      => env('JWT_SECRET', ''),
    'jwt_ttl'         => 7200,        // Token有效期（秒）
    'jwt_refresh_ttl' => 1209600,     // 刷新Token有效期（秒）
    'rate_limit'      => [
        'api'      => 60,             // 普通API每分钟请求数
        'auth'     => 10,             // 认证接口每分钟请求数
        'public'   => 30,             // 公开接口每分钟请求数
    ],
    'snowflake' => [
        'worker_id'      => 1,
        'datacenter_id'  => 1,
    ],
    'payment' => [
        'type'     => env('PAYMENT_TYPE', 'epay'),   // epay | codepay
        'api_url'  => env('PAYMENT_API_URL', ''),
        'app_id'   => env('PAYMENT_APP_ID', ''),
        'app_key'  => env('PAYMENT_APP_KEY', ''),
        'notify_url' => env('APP_URL', '') . '/api/public/payment/notify',
        'return_url' => env('APP_URL', '') . '/#/result',
    ],
    'allowed_origins' => ['*'],
];