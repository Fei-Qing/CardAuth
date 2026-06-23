-- ============================================
-- CardAuth 授权管理系统 - 数据库初始化脚本
-- 版本: 1.0.0
-- 引擎: MySQL 8.0+ / InnoDB
-- 字符集: utf8mb4
-- ============================================

CREATE DATABASE IF NOT EXISTS `card_auth` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `card_auth`;

-- ============================================
-- 1. 用户表 (管理员/项目管理员/代理)
-- ============================================
DROP TABLE IF EXISTS `ca_users`;
CREATE TABLE `ca_users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `password` VARCHAR(255) NOT NULL COMMENT '密码(bcrypt)',
    `nickname` VARCHAR(50) DEFAULT '' COMMENT '昵称',
    `email` VARCHAR(100) DEFAULT '' COMMENT '邮箱',
    `phone` VARCHAR(20) DEFAULT '' COMMENT '手机号',
    `avatar` VARCHAR(500) DEFAULT '' COMMENT '头像URL',
    `role` ENUM('admin', 'project_admin', 'agent') NOT NULL DEFAULT 'agent' COMMENT '角色: admin=超级管理员, project_admin=项目管理员, agent=代理',
    `project_ids` JSON DEFAULT NULL COMMENT '项目管理员绑定的项目ID列表(JSON数组)',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级代理/用户ID，0表示无上级',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
    `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(45) DEFAULT '' COMMENT '最后登录IP',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    KEY `idx_role` (`role`),
    KEY `idx_status` (`status`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ============================================
-- 2. 项目表
-- ============================================
DROP TABLE IF EXISTS `ca_projects`;
CREATE TABLE `ca_projects` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL COMMENT '项目名称',
    `description` VARCHAR(500) DEFAULT '' COMMENT '项目描述',
    `api_key` VARCHAR(64) NOT NULL COMMENT 'API验证密钥',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_api_key` (`api_key`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目表';

-- ============================================
-- 3. 卡密类型表 (商品类型)
-- ============================================
DROP TABLE IF EXISTS `ca_card_types`;
CREATE TABLE `ca_card_types` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '所属项目ID',
    `name` VARCHAR(50) NOT NULL COMMENT '类型名称(如:月卡、年卡、永久卡)',
    `description` VARCHAR(255) DEFAULT '' COMMENT '商品简介',
    `duration_days` INT NOT NULL DEFAULT 0 COMMENT '有效天数(0=永久)',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '售价',
    `original_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '原价',
    `agent_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '代理消耗额度',
    `sort` INT NOT NULL DEFAULT 0 COMMENT '排序',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='卡密类型表';

-- ============================================
-- 4. 卡密表 (核心)
-- ============================================
DROP TABLE IF EXISTS `ca_cards`;
CREATE TABLE `ca_cards` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `card_key` VARCHAR(64) NOT NULL COMMENT '卡密(唯一标识)',
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '所属项目ID',
    `card_type_id` BIGINT UNSIGNED NOT NULL COMMENT '卡密类型ID',
    `type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '卡密类型名称(冗余)',
    `duration_days` INT NOT NULL DEFAULT 0 COMMENT '有效天数(0=永久)',
    `status` ENUM('unused', 'used', 'disabled') NOT NULL DEFAULT 'unused' COMMENT '状态: unused=未使用, used=已使用, disabled=已禁用',
    `bind_info` JSON DEFAULT NULL COMMENT '绑定信息: {ip, device_id, mac, hostname}',
    `bound_at` DATETIME DEFAULT NULL COMMENT '绑定时间',
    `expire_time` DATETIME DEFAULT NULL COMMENT '过期时间',
    `use_user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '使用者(代理)ID',
    `order_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联订单ID',
    `remark` VARCHAR(255) DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_card_key` (`card_key`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_card_type_id` (`card_type_id`),
    KEY `idx_status` (`status`),
    KEY `idx_expire_time` (`expire_time`),
    KEY `idx_use_user_id` (`use_user_id`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='卡密表';

-- ============================================
-- 5. 代理额度表
-- ============================================
DROP TABLE IF EXISTS `ca_agents`;
CREATE TABLE `ca_agents` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '代理用户ID',
    `total_quota` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '总分配额度',
    `used_quota` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已使用额度',
    `frozen_quota` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '冻结额度',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='代理额度表';

-- ============================================
-- 6. 额度变动日志表
-- ============================================
DROP TABLE IF EXISTS `ca_agent_quota_logs`;
CREATE TABLE `ca_agent_quota_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `agent_id` BIGINT UNSIGNED NOT NULL COMMENT '代理用户ID',
    `change_type` ENUM('recharge', 'consume', 'refund', 'adjust') NOT NULL COMMENT '变动类型: recharge=充值, consume=消费, refund=退款, adjust=调整',
    `amount` DECIMAL(12,2) NOT NULL COMMENT '变动金额',
    `balance_before` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '变动前余额',
    `balance_after` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '变动后余额',
    `target_type` VARCHAR(50) DEFAULT '' COMMENT '关联类型',
    `target_id` BIGINT UNSIGNED DEFAULT 0 COMMENT '关联ID',
    `remark` VARCHAR(255) DEFAULT '' COMMENT '备注',
    `operator_id` BIGINT UNSIGNED DEFAULT 0 COMMENT '操作人ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_agent_id` (`agent_id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_change_type` (`change_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='代理额度变动日志';

-- ============================================
-- 7. 支付订单表
-- ============================================
DROP TABLE IF EXISTS `ca_orders`;
CREATE TABLE `ca_orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_no` VARCHAR(32) NOT NULL COMMENT '订单号',
    `trade_no` VARCHAR(64) DEFAULT '' COMMENT '第三方交易号',
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '项目ID',
    `card_type_id` BIGINT UNSIGNED NOT NULL COMMENT '卡密类型ID',
    `user_id` BIGINT UNSIGNED DEFAULT 0 COMMENT '用户ID(购买者)',
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '订单金额',
    `pay_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '实际支付金额',
    `pay_type` VARCHAR(20) DEFAULT '' COMMENT '支付方式: alipay, wxpay, qqpay',
    `status` ENUM('pending', 'paid', 'expired', 'refunded') NOT NULL DEFAULT 'pending' COMMENT '状态: pending=待支付, paid=已支付, expired=已过期, refunded=已退款',
    `card_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联卡密ID',
    `contact_info` VARCHAR(255) DEFAULT '' COMMENT '联系方式(用于发货)',
    `paid_at` DATETIME DEFAULT NULL COMMENT '支付时间',
    `expired_at` DATETIME DEFAULT NULL COMMENT '过期时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_trade_no` (`trade_no`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_card_id` (`card_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付订单表';

-- ============================================
-- 8. 操作日志表
-- ============================================
DROP TABLE IF EXISTS `ca_logs`;
CREATE TABLE `ca_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作用户ID',
    `action` VARCHAR(50) NOT NULL COMMENT '操作类型',
    `target_type` VARCHAR(50) DEFAULT '' COMMENT '目标类型',
    `target_id` BIGINT UNSIGNED DEFAULT 0 COMMENT '目标ID',
    `detail` JSON DEFAULT NULL COMMENT '操作详情',
    `ip` VARCHAR(45) DEFAULT '' COMMENT '操作IP',
    `user_agent` VARCHAR(500) DEFAULT '' COMMENT 'User-Agent',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_target` (`target_type`, `target_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- ============================================
-- 9. 系统配置表
-- ============================================
DROP TABLE IF EXISTS `ca_configs`;
CREATE TABLE `ca_configs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(100) NOT NULL COMMENT '配置键',
    `value` TEXT COMMENT '配置值',
    `description` VARCHAR(255) DEFAULT '' COMMENT '说明',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- ============================================
-- 10. 卡密授权记录表 (机器人QQ + 联系人QQ)
-- ============================================
DROP TABLE IF EXISTS `ca_authorizations`;
CREATE TABLE `ca_authorizations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `card_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联卡密ID(可选)',
    `card_key` VARCHAR(64) DEFAULT NULL COMMENT '卡密(可选)',
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '所属项目ID',
    `project_name` VARCHAR(100) DEFAULT '' COMMENT '项目名称(冗余)',
    `bot_qq` VARCHAR(20) NOT NULL COMMENT '机器人QQ号',
    `contact_qq` VARCHAR(20) NOT NULL COMMENT '联系人QQ号',
    `contact_name` VARCHAR(50) DEFAULT '' COMMENT '联系人名称',
    `duration_days` INT NOT NULL DEFAULT 0 COMMENT '有效天数(0=永久)',
    `status` ENUM('active', 'expired', 'revoked') NOT NULL DEFAULT 'active' COMMENT '状态: active=有效, expired=已过期, revoked=已撤销',
    `authorized_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '授权时间',
    `expire_time` DATETIME DEFAULT NULL COMMENT '授权过期时间',
    `revoked_at` DATETIME DEFAULT NULL COMMENT '撤销时间',
    `revoke_reason` VARCHAR(255) DEFAULT '' COMMENT '撤销原因',
    `operator_id` BIGINT UNSIGNED DEFAULT 0 COMMENT '操作人ID',
    `agent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '归属代理用户ID，0表示系统生成/未归属',
    `remark` VARCHAR(255) DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_bot_contact` (`bot_qq`, `contact_qq`),
    KEY `idx_bot_qq` (`bot_qq`),
    KEY `idx_contact_qq` (`contact_qq`),
    KEY `idx_card_id` (`card_id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_agent_id` (`agent_id`),
    KEY `idx_status` (`status`),
    KEY `idx_expire_time` (`expire_time`),
    KEY `idx_authorized_at` (`authorized_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='卡密授权记录表';

-- ============================================
-- 11. 优惠码表
-- ============================================
DROP TABLE IF EXISTS `ca_coupons`;
CREATE TABLE `ca_coupons` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(32) NOT NULL COMMENT '优惠码',
    `name` VARCHAR(100) DEFAULT '' COMMENT '优惠码名称/备注',
    `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '折扣百分比(0-100)',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='优惠码表';

-- ============================================
-- 初始化数据
-- ============================================

-- 初始化超级管理员 (密码: admin123456)
INSERT INTO `ca_users` (`username`, `password`, `nickname`, `role`, `status`) VALUES
('admin', '$2y$12$LJ3m4ys3OoMJ8zKR0gVNNe5hHOqFh0VhJmKqxXkGgFMfGZqXsQHqK', '超级管理员', 'admin', 1);

-- 初始化演示项目
INSERT INTO `ca_projects` (`name`, `description`, `api_key`, `status`) VALUES
('演示项目', '默认演示项目，可删除', 'ak_' . LOWER(REPLACE(UUID(), '-', '')), 1);

-- 初始化卡密类型
INSERT INTO `ca_card_types` (`project_id`, `name`, `duration_days`, `price`, `agent_cost`, `sort`, `status`) VALUES
(1, '月卡', 30, 29.90, 20.00, 1, 1),
(1, '季卡', 90, 79.90, 50.00, 2, 1),
(1, '年卡', 365, 299.00, 200.00, 3, 1),
(1, '永久卡', 0, 699.00, 500.00, 4, 1);

-- ============================================
-- 11. 黑名单表
-- ============================================
DROP TABLE IF EXISTS `ca_blacklists`;
CREATE TABLE `ca_blacklists` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='黑名单表';

-- 系统配置初始化
INSERT INTO `ca_configs` (`key`, `value`, `description`) VALUES
('site_name', 'CardAuth授权管理系统', '站点名称'),
('site_logo', '', '站点Logo'),
('card_key_prefix', 'CA', '卡密前缀'),
('order_expire_minutes', '15', '订单过期时间(分钟)'),
('smtp_host', '', 'SMTP服务器地址'),
('smtp_port', '465', 'SMTP端口'),
('smtp_user', '', 'SMTP用户名'),
('smtp_pass', '', 'SMTP密码'),
('smtp_encryption', 'ssl', 'SMTP加密方式(ssl/tls)'),
('smtp_from_email', '', '发件人邮箱'),
('smtp_from_name', 'CardAuth', '发件人名称'),
('smtp_enabled', '0', '是否启用邮件通知(1=启用,0=禁用)'),
('smtp_expire_days', '7', '到期前N天发送提醒邮件');