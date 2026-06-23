-- 代理权限与数据隔离字段迁移
-- 添加用户上下级关系、授权归属代理字段

ALTER TABLE `ca_users`
    ADD COLUMN `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级代理/用户ID，0表示无上级' AFTER `project_ids`,
    ADD KEY `idx_parent_id` (`parent_id`);

ALTER TABLE `ca_authorizations`
    ADD COLUMN `agent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '归属代理用户ID，0表示系统生成/未归属' AFTER `operator_id`,
    ADD KEY `idx_agent_id` (`agent_id`);
