-- 授权表: card_id、card_key 改为可选，去掉依赖 card_id 的唯一索引
ALTER TABLE `ca_authorizations` 
MODIFY COLUMN `card_id` BIGINT UNSIGNED NULL COMMENT '关联卡密ID(可选)',
MODIFY COLUMN `card_key` VARCHAR(64) NULL COMMENT '卡密(可选)',
DROP INDEX `uk_card_bot_contact`,
ADD UNIQUE KEY `uk_bot_contact` (`bot_qq`, `contact_qq`);
