-- 订单表添加机器人QQ和联系人QQ字段
ALTER TABLE `ca_orders` 
ADD COLUMN `bot_qq` VARCHAR(20) DEFAULT '' COMMENT '机器人QQ号' AFTER `contact_info`,
ADD COLUMN `contact_qq` VARCHAR(20) DEFAULT '' COMMENT '联系人QQ号' AFTER `bot_qq`;
