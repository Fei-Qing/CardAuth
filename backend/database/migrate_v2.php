<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4;dbname=card_auth', 'root', 'root123456');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. card_types 添加 original_price 字段
$pdo->exec("ALTER TABLE `ca_card_types` ADD COLUMN `original_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '原价' AFTER `price`");
echo "1. ca_card_types.original_price added\n";

// 2. orders 添加 coupon_code 字段
$pdo->exec("ALTER TABLE `ca_orders` ADD COLUMN `coupon_code` VARCHAR(32) DEFAULT '' COMMENT '使用优惠码' AFTER `contact_info`");
echo "2. ca_orders.coupon_code added\n";

// 3. 创建优惠码表
$pdo->exec("DROP TABLE IF EXISTS `ca_coupons`");
$pdo->exec("CREATE TABLE `ca_coupons` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(32) NOT NULL COMMENT '优惠码',
    `name` VARCHAR(100) DEFAULT '' COMMENT '优惠码名称/备注',
    `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '折扣百分比(0-100)，如20表示省20%',
    `max_use_count` INT NOT NULL DEFAULT 0 COMMENT '最大使用次数(0=不限)',
    `used_count` INT NOT NULL DEFAULT 0 COMMENT '已使用次数',
    `min_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '最低消费金额',
    `project_ids` VARCHAR(500) DEFAULT '' COMMENT '适用项目ID（逗号分隔，空=全部）',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=启用',
    `expire_at` DATETIME DEFAULT NULL COMMENT '过期时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_status` (`status`),
    KEY `idx_expire_at` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='优惠码表'");
echo "3. ca_coupons created\n";

// 4. configs 插入默认原价折扣配置
$pdo->exec("INSERT IGNORE INTO `ca_configs` (`key`, `value`, `description`) VALUES ('default_price_ratio', '1.6', '默认原价倍数（售价×倍数=原价）')");
echo "4. default_price_ratio config added\n";

echo "All migrations done.\n";