<?php
/**
 * 黑名单功能数据库迁移脚本
 * 用于在已部署的环境中创建 ca_blacklists 表
 */
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('ROUTES_PATH', BASE_PATH . '/routes');
define('STORAGE_PATH', BASE_PATH . '/storage');

require BASE_PATH . '/vendor/autoload.php';
require APP_PATH . '/Helpers/functions.php';

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

$db = App\Core\Database::getInstance();

try {
    $db->execute("DROP TABLE IF EXISTS {$db->table('blacklists')}");
    $db->execute("CREATE TABLE {$db->table('blacklists')} (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `target_type` ENUM('user', 'ip') NOT NULL DEFAULT 'ip' COMMENT '目标类型: user=用户, ip=IP地址',
        `target_value` VARCHAR(255) NOT NULL COMMENT '目标值: 用户ID或IP地址',
        `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=启用',
        `expire_time` DATETIME DEFAULT NULL COMMENT '过期时间，NULL表示永久',
        `operator_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作人ID',
        `operator_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '操作人用户名',
        `remark` VARCHAR(500) DEFAULT '' COMMENT '备注',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_target` (`target_type`, `target_value`),
        KEY `idx_status` (`status`),
        KEY `idx_expire_time` (`expire_time`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='黑名单表'");

    echo "黑名单表创建成功\n";
} catch (\Throwable $e) {
    echo "迁移失败: " . $e->getMessage() . "\n";
    exit(1);
}
