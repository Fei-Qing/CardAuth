-- 添加用户头像字段
ALTER TABLE `ca_users` ADD COLUMN `avatar` VARCHAR(500) DEFAULT '' COMMENT '头像URL' AFTER `phone`;