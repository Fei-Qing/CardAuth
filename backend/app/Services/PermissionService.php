<?php
namespace App\Services;

use App\Core\Database;

/**
 * 权限服务
 * 处理用户角色、数据范围及上下级关系
 */
class PermissionService
{
    /**
     * 判断是否为管理员（超级管理员/项目管理员）
     */
    public static function isAdmin(?string $role): bool
    {
        return in_array($role, ['admin', 'project_admin'], true);
    }

    /**
     * 判断是否为代理
     */
    public static function isAgent(?string $role): bool
    {
        return $role === 'agent';
    }

    /**
     * 获取指定用户的所有后代代理ID（包含自身）
     * 递归向上? 不，这里是获取所有下级代理
     * @return int[]
     */
    public static function getAgentIdsUnder(int $userId): array
    {
        $db = Database::getInstance();
        $ids = [$userId];
        $queue = [$userId];
        $maxDepth = 10; // 防止异常循环
        $depth = 0;

        while (!empty($queue) && $depth < $maxDepth) {
            $placeholders = implode(',', array_fill(0, count($queue), '?'));
            $children = $db->fetchAll(
                "SELECT id FROM {$db->table('users')} WHERE parent_id IN ({$placeholders}) AND role = 'agent' AND deleted_at IS NULL",
                $queue
            );
            $queue = [];
            foreach ($children as $child) {
                $childId = (int) $child['id'];
                if (!in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
            $depth++;
        }

        return $ids;
    }

    /**
     * 构建授权列表的数据范围过滤条件
     * @param array $user 当前用户（需包含 id、role、project_ids）
     * @return array ['where' => string, 'params' => array]
     */
    public static function authorizationScope(array $user): array
    {
        if (($user['role'] ?? '') === 'admin') {
            return ['where' => '', 'params' => []];
        }

        // 项目管理员：只看自己项目的授权
        if (($user['role'] ?? '') === 'project_admin') {
            $projectIds = $user['project_ids'] ?? [];
            if (empty($projectIds)) {
                return ['where' => ' AND 1=0', 'params' => []];
            }
            $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
            return [
                'where' => " AND a.project_id IN ({$placeholders})",
                'params' => $projectIds,
            ];
        }

        // 代理
        $agentIds = self::getAgentIdsUnder((int) $user['id']);
        if (empty($agentIds)) {
            return ['where' => ' AND a.agent_id = 0', 'params' => []];
        }

        $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
        return [
            'where' => " AND a.agent_id IN ({$placeholders})",
            'params' => $agentIds,
        ];
    }

    /**
     * 构建卡密列表的数据范围过滤条件
     * @param array $user 当前用户（需包含 id、role、project_ids）
     * @return array ['where' => string, 'params' => array]
     */
    public static function cardScope(array $user): array
    {
        if (($user['role'] ?? '') === 'admin') {
            return ['where' => '', 'params' => []];
        }

        // 项目管理员：只看自己项目的卡密
        if (($user['role'] ?? '') === 'project_admin') {
            $projectIds = $user['project_ids'] ?? [];
            if (empty($projectIds)) {
                return ['where' => ' AND 1=0', 'params' => []];
            }
            $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
            return [
                'where' => " AND c.project_id IN ({$placeholders})",
                'params' => $projectIds,
            ];
        }

        // 代理
        $agentIds = self::getAgentIdsUnder((int) $user['id']);
        if (empty($agentIds)) {
            return ['where' => ' AND c.use_user_id = 0', 'params' => []];
        }

        $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
        return [
            'where' => " AND c.use_user_id IN ({$placeholders})",
            'params' => $agentIds,
        ];
    }
}
