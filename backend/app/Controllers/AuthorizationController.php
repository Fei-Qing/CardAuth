<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\PermissionService;

/**
 * 卡密授权控制器
 * 管理机器人QQ与联系人QQ的授权绑定关系
 */
class AuthorizationController extends Controller
{
    /**
     * 授权列表
     * GET /api/authorizations?bot_qq=&contact_qq=&status=&project_id=&card_key=&page=&page_size=
     */
    public function list(): void
    {
        $db = Database::getInstance();
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize   = min((int) ($_GET['page_size'] ?? 20), 100);
        $offset     = ($page - 1) * $pageSize;
        $botQq      = trim($_GET['bot_qq'] ?? '');
        $contactQq  = trim($_GET['contact_qq'] ?? '');
        $status     = $_GET['status'] ?? '';
        $projectId  = $_GET['project_id'] ?? '';
        $cardKey    = trim($_GET['card_key'] ?? '');
        $isExpired  = $_GET['is_expired'] ?? '';

        $where = 'WHERE 1=1';
        $params = [];

        // 数据范围权限过滤（管理员查看全部，代理仅查看自身及下级代理的授权）
        $scope = PermissionService::authorizationScope($this->getCurrentUser());
        $where .= $scope['where'];
        $params = array_merge($params, $scope['params']);

        if ($botQq) {
            $where .= ' AND a.bot_qq = ?';
            $params[] = $botQq;
        }
        if ($contactQq) {
            $where .= ' AND a.contact_qq = ?';
            $params[] = $contactQq;
        }
        if ($status) {
            $where .= ' AND a.status = ?';
            $params[] = $status;
        }
        if ($projectId) {
            $where .= ' AND a.project_id = ?';
            $params[] = $projectId;
        }
        if ($cardKey) {
            $where .= ' AND a.card_key LIKE ?';
            $params[] = "%{$cardKey}%";
        }
        if ($isExpired === '1') {
            $where .= ' AND a.status = \'active\' AND a.expire_time IS NOT NULL AND a.expire_time < NOW()';
        }
        if ($isExpired === '0') {
            $where .= ' AND (a.expire_time IS NULL OR a.expire_time >= NOW() OR a.status != \'active\')';
        }

        $total = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('authorizations')} a {$where}",
            $params
        );

        $rows = $db->fetchAll(
            "SELECT a.*, p.name AS project_name, ct.name AS card_type_name
             FROM {$db->table('authorizations')} a
             LEFT JOIN {$db->table('projects')} p ON a.project_id = p.id
             LEFT JOIN {$db->table('cards')} c ON a.card_id = c.id
             LEFT JOIN {$db->table('card_types')} ct ON c.card_type_id = ct.id
             {$where}
             ORDER BY a.id DESC
             LIMIT {$offset}, {$pageSize}",
            $params
        );

        // 实时计算过期状态
        $now = time();
        foreach ($rows as &$row) {
            $row['is_expired'] = $row['status'] === 'active'
                && $row['expire_time']
                && strtotime($row['expire_time']) < $now;
        }

        $this->success([
            'list'      => $rows,
            'total'     => (int) $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 授权详情
     * GET /api/authorizations/{id}
     */
    public function detail(int $id): void
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT a.*, p.name AS project_name, ct.name AS card_type_name
             FROM {$db->table('authorizations')} a
             LEFT JOIN {$db->table('projects')} p ON a.project_id = p.id
             LEFT JOIN {$db->table('cards')} c ON a.card_id = c.id
             LEFT JOIN {$db->table('card_types')} ct ON c.card_type_id = ct.id
             WHERE a.id = ?",
            [$id]
        );

        if (!$row) {
            $this->error('授权记录不存在', 404);
        }

        $row['is_expired'] = $row['status'] === 'active'
            && $row['expire_time']
            && strtotime($row['expire_time']) < time();

        $this->success($row);
    }

    /**
     * 新增授权（绑定卡密到机器人QQ/联系人QQ）
     * POST /api/authorizations
     * Body: { card_key, bot_qq, contact_qq, contact_name? }
     */
    public function create(): void
    {
        $input = $this->getJsonInput();
        $projectId   = (int) ($input['project_id'] ?? 0);
        $cardTypeId  = (int) ($input['card_type_id'] ?? 0);
        $botQq       = trim($input['bot_qq'] ?? '');
        $contactQq   = trim($input['contact_qq'] ?? '');
        $contactName = trim($input['contact_name'] ?? '');

        // 参数校验
        if (empty($projectId)) {
            $this->error('请选择项目');
        }
        if (empty($cardTypeId)) {
            $this->error('请选择套餐');
        }
        if (empty($botQq)) {
            $this->error('机器人QQ不能为空');
        }
        if (empty($contactQq)) {
            $this->error('联系人QQ不能为空');
        }
        if (!preg_match('/^\d{5,15}$/', $botQq)) {
            $this->error('机器人QQ格式不正确');
        }
        if (!preg_match('/^\d{5,15}$/', $contactQq)) {
            $this->error('联系人QQ格式不正确');
        }

        $db = Database::getInstance();
        $userId      = $this->getUserId() ?? 0;
        $currentRole = $this->getUserRole();

        // 验证项目存在
        $project = $db->fetch("SELECT * FROM {$db->table('projects')} WHERE id = ? AND deleted_at IS NULL", [$projectId]);
        if (!$project) {
            $this->error('项目不存在');
        }

        // 验证套餐
        $cardType = $db->fetch(
            "SELECT * FROM {$db->table('card_types')} WHERE id = ? AND project_id = ?",
            [$cardTypeId, $projectId]
        );
        if (!$cardType) {
            $this->error('套餐不存在或不属于该项目');
        }

        // 项目管理员：只能在自己负责的项目中创建授权
        if ($currentRole === 'project_admin') {
            $currentUser = $this->getCurrentUser();
            $projectIds = $currentUser['project_ids'] ?? [];
            if (!in_array($projectId, $projectIds)) {
                $this->error('无权在此项目中创建授权', 403);
            }
        }

        // 代理扣费
        $needDeductQuota = false;
        $deductAmount = 0;
        $quotaBefore = 0;

        if ($currentRole === 'agent') {
            if ((float) $cardType['agent_cost'] <= 0) {
                $this->error('该套餐未配置代理价格，无法创建授权');
            }

            $agentAccount = $db->fetch("SELECT * FROM {$db->table('agents')} WHERE user_id = ?", [$userId]);
            if (!$agentAccount) {
                $this->error('代理额度账户不存在，请联系管理员充值');
            }

            $remainQuota = (float) ($agentAccount['total_quota'] ?? 0) - (float) ($agentAccount['used_quota'] ?? 0) - (float) ($agentAccount['frozen_quota'] ?? 0);

            if ($remainQuota < (float) $cardType['agent_cost']) {
                $this->error('可用额度不足，需要 ¥' . $cardType['agent_cost'] . '，当前剩余 ¥' . $remainQuota);
            }

            $needDeductQuota = true;
            $deductAmount = (float) $cardType['agent_cost'];
            $quotaBefore = $remainQuota;
        }

        // 计算过期时间
        $expireTime = null;
        if ($cardType['duration_days'] > 0) {
            $expireTime = date('Y-m-d H:i:s', strtotime("+{$cardType['duration_days']} days"));
        }

        // 确定 agent_id
        $agentId = 0;
        if ($currentRole === 'agent') {
            $agentId = $userId;
        }

        // 写入授权记录
        $authId = $db->insert(
            "INSERT INTO {$db->table('authorizations')}
             (card_id, card_key, project_id, project_name, bot_qq, contact_qq,
              contact_name, duration_days, status, expire_time, operator_id, agent_id, authorized_at, created_at)
             VALUES (0, '', ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW(), NOW())",
            [
                $projectId,
                $project['name'] ?? '',
                $botQq,
                $contactQq,
                $contactName,
                (int) $cardType['duration_days'],
                $expireTime,
                $userId,
                $agentId,
            ]
        );

        // 如果需要扣减代理额度
        if ($needDeductQuota) {
            $db->execute(
                "UPDATE {$db->table('agents')} SET used_quota = used_quota + ? WHERE user_id = ?",
                [$deductAmount, $userId]
            );

            $quotaAfter = $quotaBefore - $deductAmount;

            // 记录额度变动
            $db->insert(
                "INSERT INTO {$db->table('agent_quota_logs')} (agent_id, change_type, amount, balance_before, balance_after, target_type, target_id, remark, operator_id, created_at)
                 VALUES (?, 'consume', ?, ?, ?, 'authorization', ?, '授权扣费', ?, NOW())",
                [$userId, $deductAmount, $quotaBefore, $quotaAfter, $authId, $this->getUserId()]
            );
        }

        // 记录操作日志
        $this->logAction('authorization_create', 'authorization', $authId, [
            'project_id'  => $projectId,
            'card_type_id'=> $cardTypeId,
            'bot_qq'      => $botQq,
            'contact_qq'  => $contactQq,
        ]);

        $this->success([
            'id'          => $authId,
            'bot_qq'      => $botQq,
            'contact_qq'  => $contactQq,
            'expire_time' => $expireTime,
        ], '授权成功');
    }

    /**
     * 公开在线授权（使用卡密激活机器人授权）
     * POST /api/public/authorizations/activate
     * Body: { card_key, bot_qq, contact_qq, contact_name? }
     */
    public function publicActivate(): void
    {
        $input = $this->getJsonInput();
        $cardKey     = trim($input['card_key'] ?? '');
        $botQq       = trim($input['bot_qq'] ?? '');
        $contactQq   = trim($input['contact_qq'] ?? '');
        $contactName = trim($input['contact_name'] ?? '');

        if (empty($cardKey)) {
            $this->error('卡密不能为空');
        }
        if (empty($botQq)) {
            $this->error('机器人QQ不能为空');
        }
        if (empty($contactQq)) {
            $this->error('联系人QQ不能为空');
        }
        if (!preg_match('/^\d{5,15}$/', $botQq)) {
            $this->error('机器人QQ格式不正确');
        }
        if (!preg_match('/^\d{5,15}$/', $contactQq)) {
            $this->error('联系人QQ格式不正确');
        }

        $db = Database::getInstance();

        $card = $db->fetch(
            "SELECT c.*, p.name AS project_name
             FROM {$db->table('cards')} c
             LEFT JOIN {$db->table('projects')} p ON c.project_id = p.id
             WHERE c.card_key = ?",
            [$cardKey]
        );

        if (!$card) {
            $this->error('卡密不存在');
        }

        if ($card['status'] === 'disabled') {
            $this->error('该卡密已被禁用');
        }

        if ($card['status'] === 'used') {
            $this->error('该卡密已被使用');
        }

        // 卡密归属与使用权限验证 + 代理扣费
        $currentUserId = $this->getUserId() ?? 0;
        $currentRole = $this->getUserRole();
        $cardAgentId = (int) ($card['use_user_id'] ?? 0);
        $needDeductQuota = false;

        if ($cardAgentId > 0) {
            // 该卡密是某个代理生成的（已扣费）
            // 公开接口无登录态 → 拒绝使用代理卡密
            if (empty($currentUserId)) {
                $this->error('该卡密需要登录后使用，请先登录');
            }
            // 非归属代理 → 禁止使用他人卡密
            if ($cardAgentId !== $currentUserId) {
                $this->error('此卡密不属于您，无法使用');
            }
            // 归属代理本人 → 允许（生成时已扣费）
        } else {
            // 该卡密非代理生成（管理员生成或其他渠道）
            // 如果当前用户是代理，使用此类卡密需按代理价格扣费
            if ($currentRole === 'agent' && $currentUserId > 0) {
                $cardTypeForCost = $db->fetch(
                    "SELECT agent_cost FROM {$db->table('card_types')} WHERE id = ? AND project_id = ?",
                    [$card['card_type_id'], $card['project_id']]
                );

                if (!$cardTypeForCost || (float) $cardTypeForCost['agent_cost'] <= 0) {
                    $this->error('该卡密套餐未配置代理价格，无法使用');
                }

                $agentAccount = $db->fetch("SELECT * FROM {$db->table('agents')} WHERE user_id = ?", [$currentUserId]);
                if (!$agentAccount) {
                    $this->error('代理额度账户不存在，请联系管理员充值');
                }

                $remainQuota = (float) ($agentAccount['total_quota'] ?? 0) - (float) ($agentAccount['used_quota'] ?? 0) - (float) ($agentAccount['frozen_quota'] ?? 0);

                if ($remainQuota < (float) $cardTypeForCost['agent_cost']) {
                    $this->error('可用额度不足，需要 ¥' . $cardTypeForCost['agent_cost'] . '，当前剩余 ¥' . $remainQuota);
                }

                $needDeductQuota = true;
                $deductAmount = (float) $cardTypeForCost['agent_cost'];
                $quotaBefore = $remainQuota;
            }
        }

        // 检查当前机器人QQ在同一项目下是否已有授权（未撤销），有则续费
        $existingAuth = $db->fetch(
            "SELECT * FROM {$db->table('authorizations')}
             WHERE project_id = ? AND bot_qq = ? AND status != 'revoked'
             ORDER BY expire_time IS NULL DESC, expire_time DESC, id DESC
             LIMIT 1",
            [$card['project_id'], $botQq]
        );

        $db->execute(
            "UPDATE {$db->table('cards')} SET status = 'used', bound_at = NOW() WHERE id = ?",
            [$card['id']]
        );

        $agentId = (int) ($card['use_user_id'] ?? 0);

        if ($existingAuth) {
            // 续费：在原有过期时间基础上累加
            $baseTime = null;
            if ($existingAuth['expire_time']) {
                $baseTime = max(date('Y-m-d H:i:s'), $existingAuth['expire_time']);
            }

            $newExpireTime = null;
            if ($card['duration_days'] > 0) {
                if ($baseTime) {
                    $newExpireTime = date('Y-m-d H:i:s', strtotime("+{$card['duration_days']} days", strtotime($baseTime)));
                } else {
                    // 原授权为永久，新卡密有时长：从当前时间开始加
                    $newExpireTime = date('Y-m-d H:i:s', strtotime("+{$card['duration_days']} days"));
                }
            } else {
                // 新卡密为永久卡，直接变为永久
                $newExpireTime = null;
            }

            $newDuration = (int) $existingAuth['duration_days'] + (int) $card['duration_days'];

            $db->execute(
                "UPDATE {$db->table('authorizations')}
                 SET expire_time = ?, duration_days = ?, status = 'active', contact_qq = ?, contact_name = ?, updated_at = NOW()
                 WHERE id = ?",
                [$newExpireTime, $newDuration, $contactQq, $contactName, $existingAuth['id']]
            );

            // 记录使用记录（便于追踪每次续费卡密）
            $renewAuthId = $db->insert(
                "INSERT INTO {$db->table('authorizations')}
                 (card_id, card_key, project_id, project_name, bot_qq, contact_qq,
                  contact_name, duration_days, status, expire_time, operator_id, agent_id, authorized_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW(), NOW())",
                [
                    $card['id'],
                    $card['card_key'],
                    $card['project_id'],
                    $card['project_name'] ?? '',
                    $botQq,
                    $contactQq,
                    $contactName,
                    (int) $card['duration_days'],
                    $newExpireTime,
                    0,
                    $agentId,
                ]
            );

            // 如果需要扣减代理额度（续费场景）
            if ($needDeductQuota) {
                $db->execute(
                    "UPDATE {$db->table('agents')} SET used_quota = used_quota + ? WHERE user_id = ?",
                    [$deductAmount, $currentUserId]
                );

                $quotaAfter = $quotaBefore - $deductAmount;

                // 记录额度变动
                $db->insert(
                    "INSERT INTO {$db->table('agent_quota_logs')} (agent_id, change_type, amount, balance_before, balance_after, target_type, target_id, remark, operator_id, created_at)
                     VALUES (?, 'consume', ?, ?, ?, 'authorization', ?, '续费扣费', ?, NOW())",
                    [$currentUserId, $deductAmount, $quotaBefore, $quotaAfter, $renewAuthId, $this->getUserId()]
                );
            }

            $this->success([
                'id'          => (int) $existingAuth['id'],
                'card_key'    => $cardKey,
                'bot_qq'      => $botQq,
                'contact_qq'  => $contactQq,
                'contact_name'=> $contactName,
                'project_id'  => (int) $card['project_id'],
                'project_name'=> $card['project_name'] ?? '',
                'expire_time' => $newExpireTime,
                'is_renew'    => true,
            ], '续费成功');
            return;
        }

        $expireTime = null;
        if ($card['duration_days'] > 0) {
            $expireTime = date('Y-m-d H:i:s', strtotime("+{$card['duration_days']} days"));
        }

        $authId = $db->insert(
            "INSERT INTO {$db->table('authorizations')}
             (card_id, card_key, project_id, project_name, bot_qq, contact_qq,
              contact_name, duration_days, status, expire_time, operator_id, agent_id, authorized_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW(), NOW())",
            [
                $card['id'],
                $card['card_key'],
                $card['project_id'],
                $card['project_name'] ?? '',
                $botQq,
                $contactQq,
                $contactName,
                (int) $card['duration_days'],
                $expireTime,
                0,
                $agentId,
            ]
        );

        // 如果需要扣减代理额度（新授权场景）
        if ($needDeductQuota) {
            $db->execute(
                "UPDATE {$db->table('agents')} SET used_quota = used_quota + ? WHERE user_id = ?",
                [$deductAmount, $currentUserId]
            );

            $quotaAfter = $quotaBefore - $deductAmount;

            // 记录额度变动
            $db->insert(
                "INSERT INTO {$db->table('agent_quota_logs')} (agent_id, change_type, amount, balance_before, balance_after, target_type, target_id, remark, operator_id, created_at)
                 VALUES (?, 'consume', ?, ?, ?, 'authorization', ?, '授权扣费', ?, NOW())",
                [$currentUserId, $deductAmount, $quotaBefore, $quotaAfter, $authId, $this->getUserId()]
            );
        }

        $this->success([
            'id'          => $authId,
            'card_key'    => $cardKey,
            'bot_qq'      => $botQq,
            'contact_qq'  => $contactQq,
            'contact_name'=> $contactName,
            'project_id'  => (int) $card['project_id'],
            'project_name'=> $card['project_name'] ?? '',
            'expire_time' => $expireTime,
            'is_renew'    => false,
        ], '授权成功');
    }

    /**
     * 撤销授权
     * PUT /api/authorizations/{id}/revoke
     * Body: { reason? }
     */
    public function revoke(int $id): void
    {
        $db = Database::getInstance();
        $auth = $db->fetch("SELECT * FROM {$db->table('authorizations')} WHERE id = ?", [$id]);

        if (!$auth) {
            $this->error('授权记录不存在', 404);
        }

        if ($auth['status'] === 'revoked') {
            $this->error('授权已被撤销');
        }

        $input = $this->getJsonInput();
        $reason = $input['reason'] ?? '';

        $db->execute(
            "UPDATE {$db->table('authorizations')}
             SET status = 'revoked', revoked_at = NOW(), revoke_reason = ?, updated_at = NOW()
             WHERE id = ?",
            [$reason, $id]
        );

        $this->logAction('authorization_revoke', 'authorization', $id, [
            'card_key'   => $auth['card_key'],
            'bot_qq'     => $auth['bot_qq'],
            'contact_qq' => $auth['contact_qq'],
            'reason'     => $reason,
        ]);

        $this->success(null, '授权已撤销');
    }

    /**
     * 删除授权记录
     * DELETE /api/authorizations/{id}
     */
    public function delete(int $id): void
    {
        $db = Database::getInstance();
        $auth = $db->fetch("SELECT * FROM {$db->table('authorizations')} WHERE id = ?", [$id]);

        if (!$auth) {
            $this->error('授权记录不存在', 404);
        }

        $db->execute("DELETE FROM {$db->table('authorizations')} WHERE id = ?", [$id]);

        $this->logAction('authorization_delete', 'authorization', $id, [
            'card_key'   => $auth['card_key'],
            'bot_qq'     => $auth['bot_qq'],
            'contact_qq' => $auth['contact_qq'],
        ]);

        $this->success(null, '授权记录已删除');
    }

    /**
     * 批量删除授权记录
     * POST /api/authorizations/batch-delete
     * Body: { ids: [int] }
     */
    public function batchDelete(): void
    {
        $input = $this->getJsonInput();
        $ids = $input['ids'] ?? [];

        if (!is_array($ids) || empty($ids)) {
            $this->error('请选择要删除的授权记录');
        }

        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            $this->error('无效的授权ID');
        }

        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $this->logAction('authorization_batch_delete', 'authorization', 0, ['count' => count($ids)]);

        $db->execute(
            "DELETE FROM {$db->table('authorizations')} WHERE id IN ({$placeholders})",
            $ids
        );

        $this->success(['count' => count($ids)], '批量删除成功');
    }

    /**
     * 导出授权记录
     * GET /api/authorizations/export
     * Query: ids, format(csv|excel)
     */
    public function export(): void
    {
        $ids    = $_GET['ids'] ?? [];
        $format = strtolower($_GET['format'] ?? 'csv');
        if (!in_array($format, ['csv', 'excel'])) {
            $format = 'csv';
        }

        $db = Database::getInstance();
        $where = 'WHERE 1=1';
        $params = [];

        if (!empty($ids)) {
            $ids = is_array($ids) ? $ids : explode(',', $ids);
            $ids = array_filter(array_map('intval', $ids));
            if (!empty($ids)) {
                $where .= ' AND a.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = array_merge($params, $ids);
            }
        }

        $rows = $db->fetchAll(
            "SELECT a.*, p.name AS project_name, ct.name AS card_type_name
             FROM {$db->table('authorizations')} a
             LEFT JOIN {$db->table('projects')} p ON a.project_id = p.id
             LEFT JOIN {$db->table('cards')} c ON a.card_id = c.id
             LEFT JOIN {$db->table('card_types')} ct ON c.card_type_id = ct.id
             {$where}
             ORDER BY a.id DESC
             LIMIT 50000",
            $params
        );

        $statusMap = ['active' => '有效', 'revoked' => '已撤销'];

        if ($format === 'excel') {
            $filename = 'authorizations_export_' . date('YmdHis') . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache');
            $html = '<table border="1"><tr><th>ID</th><th>机器人QQ</th><th>联系人QQ</th><th>项目</th><th>卡密</th><th>有效天数</th><th>状态</th><th>过期时间</th><th>授权时间</th></tr>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($row['id']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['bot_qq']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['contact_qq']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['project_name'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['card_key']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['duration_days'] == 0 ? '永久' : $row['duration_days'] . '天') . '</td>';
                $html .= '<td>' . htmlspecialchars($statusMap[$row['status']] ?? $row['status']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['expire_time'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['authorized_at']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            echo $html;
            exit;
        }

        $filename = 'authorizations_export_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['ID', '机器人QQ', '联系人QQ', '项目', '卡密', '有效天数', '状态', '过期时间', '授权时间']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['bot_qq'],
                $row['contact_qq'],
                $row['project_name'] ?? '',
                $row['card_key'],
                $row['duration_days'] == 0 ? '永久' : $row['duration_days'] . '天',
                $statusMap[$row['status']] ?? $row['status'],
                $row['expire_time'] ?? '',
                $row['authorized_at'],
            ]);
        }
        fclose($output);
        exit;
    }

    // ========================================
    // 公开接口（无需认证）
    // ========================================

    /**
     * 按机器人QQ查询授权状态（主要查询方式）
     * GET /api/public/authorizations/query?bot_qq=
     */
    public function queryByBot(): void
    {
        $botQq = trim($_GET['bot_qq'] ?? '');

        if (empty($botQq)) {
            $this->error('请输入机器人QQ号');
        }

        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $rows = $db->fetchAll(
            "SELECT a.id, a.card_key, a.project_name, a.bot_qq, a.contact_qq, a.contact_name,
                    a.duration_days, a.status, a.authorized_at, a.expire_time,
                    p.name AS project_name_full
             FROM {$db->table('authorizations')} a
             LEFT JOIN {$db->table('projects')} p ON a.project_id = p.id
             WHERE a.bot_qq = ?
             ORDER BY a.id DESC",
            [$botQq]
        );

        $activeCount = 0;
        $expiredCount = 0;
        $revokedCount = 0;

        foreach ($rows as &$row) {
            $row['is_expired'] = $row['status'] === 'active'
                && $row['expire_time']
                && strtotime($row['expire_time']) < time();

            if ($row['is_expired']) {
                $expiredCount++;
            } elseif ($row['status'] === 'active') {
                $activeCount++;
            } elseif ($row['status'] === 'revoked') {
                $revokedCount++;
            }
        }

        $this->success([
            'bot_qq'         => $botQq,
            'total'          => count($rows),
            'active_count'   => $activeCount,
            'expired_count'  => $expiredCount,
            'revoked_count'  => $revokedCount,
            'has_valid_auth' => $activeCount > 0,
            'list'           => $rows,
        ]);
    }

    /**
     * 按联系人QQ查询授权状态
     * GET /api/public/authorizations/query-by-contact?contact_qq=
     */
    public function queryByContact(): void
    {
        $contactQq = trim($_GET['contact_qq'] ?? '');

        if (empty($contactQq)) {
            $this->error('请输入联系人QQ号');
        }

        $db = Database::getInstance();

        $rows = $db->fetchAll(
            "SELECT a.id, a.card_key, a.project_name, a.bot_qq, a.contact_qq, a.contact_name,
                    a.duration_days, a.status, a.authorized_at, a.expire_time
             FROM {$db->table('authorizations')} a
             WHERE a.contact_qq = ?
             ORDER BY a.id DESC",
            [$contactQq]
        );

        foreach ($rows as &$row) {
            $row['is_expired'] = $row['status'] === 'active'
                && $row['expire_time']
                && strtotime($row['expire_time']) < time();
        }

        $this->success([
            'contact_qq' => $contactQq,
            'total'      => count($rows),
            'list'       => $rows,
        ]);
    }

    /**
     * 验证授权有效性（客户端调用）
     * POST /api/public/authorizations/verify
     * Body: { bot_qq, card_key?, contact_qq? }
     */
    public function verify(): void
    {
        $input = $this->getJsonInput();
        $botQq     = trim($input['bot_qq'] ?? '');
        $cardKey   = trim($input['card_key'] ?? '');
        $contactQq = trim($input['contact_qq'] ?? '');

        if (empty($botQq)) {
            $this->error('机器人QQ不能为空');
        }

        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $where = 'WHERE a.bot_qq = ?';
        $params = [$botQq];

        if ($cardKey) {
            $where .= ' AND a.card_key = ?';
            $params[] = $cardKey;
        }
        if ($contactQq) {
            $where .= ' AND a.contact_qq = ?';
            $params[] = $contactQq;
        }

        $where .= " AND a.status = 'active' AND (a.expire_time IS NULL OR a.expire_time >= ?)";
        $params[] = $now;

        $auth = $db->fetch(
            "SELECT a.* FROM {$db->table('authorizations')} a {$where} ORDER BY a.id DESC LIMIT 1",
            $params
        );

        if (!$auth) {
            $this->success([
                'valid'        => false,
                'bot_qq'       => $botQq,
                'message'      => '未找到有效授权',
                'has_valid_auth' => false,
            ]);
            return;
        }

        $this->success([
            'valid'        => true,
            'bot_qq'       => $auth['bot_qq'],
            'contact_qq'   => $auth['contact_qq'],
            'card_key'     => $auth['card_key'],
            'project_name' => $auth['project_name'],
            'expire_time'  => $auth['expire_time'],
            'days_left'    => $auth['expire_time']
                ? max(0, (int) ceil((strtotime($auth['expire_time']) - time()) / 86400))
                : -1,
            'has_valid_auth' => true,
            'message'      => '授权有效',
        ]);
    }

    /**
     * 授权统计
     * GET /api/authorizations/stats
     */
    public function stats(): void
    {
        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $total = $db->fetchColumn("SELECT COUNT(*) FROM {$db->table('authorizations')}");
        $active = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('authorizations')}
             WHERE status = 'active' AND (expire_time IS NULL OR expire_time >= ?)",
            [$now]
        );
        $expired = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('authorizations')}
             WHERE status = 'active' AND expire_time IS NOT NULL AND expire_time < ?",
            [$now]
        );
        $revoked = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('authorizations')} WHERE status = 'revoked'"
        );
        $todayNew = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('authorizations')} WHERE DATE(authorized_at) = CURDATE()"
        );

        $this->success([
            'total'      => (int) $total,
            'active'     => (int) $active,
            'expired'    => (int) $expired,
            'revoked'    => (int) $revoked,
            'today_new'  => (int) $todayNew,
        ]);
    }

    /**
     * 记录操作日志
     */
    private function logAction(string $action, string $targetType, int $targetId, array $detail = []): void
    {
        try {
            $db = Database::getInstance();
            $userId = (int) ($_SERVER['HTTP_X_USER_ID'] ?? 0);
            $db->insert(
                "INSERT INTO {$db->table('logs')} (user_id, action, target_type, target_id, detail, ip, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $userId,
                    $action,
                    $targetType,
                    $targetId,
                    json_encode($detail, JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                ]
            );
        } catch (\Throwable $e) {
            // 日志写入失败不影响主流程
        }
    }
}