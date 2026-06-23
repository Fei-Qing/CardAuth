<?php
/**
 * 全局辅助函数
 */

/**
 * 获取配置值
 */
function config(string $key, mixed $default = null): mixed
{
    static $configs = [];
    $keys = explode('.', $key, 2);

    if (!isset($configs[$keys[0]])) {
        $file = CONFIG_PATH . '/' . $keys[0] . '.php';
        if (file_exists($file)) {
            $configs[$keys[0]] = require $file;
        } else {
            return $default;
        }
    }

    if (count($keys) === 1) {
        return $configs[$keys[0]];
    }

    return dotGet($configs[$keys[0]], $keys[1], $default);
}

/**
 * 点号访问数组
 */
function dotGet(array $array, string $key, mixed $default = null): mixed
{
    $keys = explode('.', $key);
    foreach ($keys as $segment) {
        if (!is_array($array) || !array_key_exists($segment, $array)) {
            return $default;
        }
        $array = $array[$segment];
    }
    return $array;
}

/**
 * 获取环境变量
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false) return $default;

    return match (strtolower($value)) {
        'true', '(true)'  => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        default            => $value,
    };
}

/**
 * 获取数据库实例
 */
function db(): \App\Core\Database
{
    return \App\Core\Database::getInstance();
}

/**
 * 生成UUID v4
 */
function uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * 生成随机字符串
 */
function randomStr(int $length = 32): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $str;
}

/**
 * 密码哈希
 */
function passwordHash(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * 验证密码
 */
function passwordVerify(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * 获取客户端IP
 */
function clientIp(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * 获取User-Agent
 */
function userAgent(): string
{
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

/**
 * 记录日志（简化版）
 */
function logger(string $action, string $targetType = '', int $targetId = 0, array $detail = []): void
{
    try {
        $db = db();
        $db->insert("INSERT INTO {$db->table('logs')} (user_id, action, target_type, target_id, detail, ip, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())", [
            $_SERVER['HTTP_X_USER_ID'] ?? 0,
            $action,
            $targetType,
            $targetId,
            json_encode($detail, JSON_UNESCAPED_UNICODE),
            clientIp(),
            userAgent(),
        ]);
    } catch (\Throwable $e) {
        error_log('Logger error: ' . $e->getMessage());
    }
}