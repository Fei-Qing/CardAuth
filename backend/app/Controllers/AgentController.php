<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;

/**
 * 代理与额度管理控制器
 */
class AgentController extends Controller
{
    /**
     * 代理列表
     */
    /**
     * 代理列表
     * GET /api/agents
     * Query: keyword, status, sort_by, sort_order, page, page_size
     */
    public function list(): void
    {
        $db = Database::getInstance();
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize   = min((int) ($_GET['page_size'] ?? 20), 100);
        $keyword    = trim($_GET['keyword'] ?? '');
        $status     = $_GET['status'] ?? '';
        $offset     = ($page - 1) * $pageSize;

        $where = "WHERE u.role = 'agent' AND u.deleted_at IS NULL";
        $params = [];

        if ($status !== '') {
            $where .= " AND u.status = ?";
            $params[] = (int) $status;
        }
        if ($keyword) {
            $where .= " AND (u.username LIKE ? OR u.nickname LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }

        $total = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('users')} u {$where}",
            $params
        );

        $sortBy    = in_array($_GET['sort_by'] ?? '', ['id', 'username', 'total_quota', 'used_quota', 'remain_quota', 'created_at']) ? $_GET['sort_by'] : 'id';
        $sortOrder = in_array(strtolower($_GET['sort_order'] ?? ''), ['asc', 'desc']) ? strtoupper($_GET['sort_order']) : 'DESC';
        $orderSql  = $sortBy === 'remain_quota' ? "(COALESCE(a.total_quota, 0) - COALESCE(a.used_quota, 0) - COALESCE(a.frozen_quota, 0)) {$sortOrder}" : "u.{$sortBy} {$sortOrder}";

        $agents = $db->fetchAll(
            "SELECT u.id, u.username, u.nickname, u.email, u.status, u.last_login_at, u.created_at,
                    COALESCE(a.total_quota, 0) AS total_quota,
                    COALESCE(a.used_quota, 0) AS used_quota,
                    COALESCE(a.frozen_quota, 0) AS frozen_quota,
                    (COALESCE(a.total_quota, 0) - COALESCE(a.used_quota, 0) - COALESCE(a.frozen_quota, 0)) AS remain_quota
             FROM {$db->table('users')} u
             LEFT JOIN {$db->table('agents')} a ON u.id = a.user_id
             {$where}
             ORDER BY {$orderSql}
             LIMIT {$offset}, {$pageSize}",
            $params
        );

        $this->success([
            'list'      => $agents,
            'total'     => (int) $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 导出代理
     * GET /api/agents/export
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
        $where = "WHERE u.role = 'agent' AND u.deleted_at IS NULL";
        $params = [];

        if (!empty($ids)) {
            $ids = is_array($ids) ? $ids : explode(',', $ids);
            $ids = array_filter(array_map('intval', $ids));
            if (!empty($ids)) {
                $where .= ' AND u.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = array_merge($params, $ids);
            }
        }

        $rows = $db->fetchAll(
            "SELECT u.id, u.username, u.nickname, u.email, u.status, u.created_at,
                    COALESCE(a.total_quota, 0) AS total_quota,
                    COALESCE(a.used_quota, 0) AS used_quota,
                    (COALESCE(a.total_quota, 0) - COALESCE(a.used_quota, 0) - COALESCE(a.frozen_quota, 0)) AS remain_quota
             FROM {$db->table('users')} u
             LEFT JOIN {$db->table('agents')} a ON u.id = a.user_id
             {$where}
             ORDER BY u.id DESC
             LIMIT 50000",
            $params
        );

        if ($format === 'excel') {
            $filename = 'agents_export_' . date('YmdHis') . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache');
            $html = '<table border="1"><tr><th>ID</th><th>用户名</th><th>昵称</th><th>邮箱</th><th>状态</th><th>总额度</th><th>已使用</th><th>剩余额度</th><th>创建时间</th></tr>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($row['id']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['username']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['nickname'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['status'] == 1 ? '正常' : '禁用') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['total_quota']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['used_quota']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['remain_quota']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            echo $html;
            exit;
        }

        $filename = 'agents_export_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['ID', '用户名', '昵称', '邮箱', '状态', '总额度', '已使用', '剩余额度', '创建时间']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['username'],
                $row['nickname'] ?? '',
                $row['email'] ?? '',
                $row['status'] == 1 ? '正常' : '禁用',
                $row['total_quota'],
                $row['used_quota'],
                $row['remain_quota'],
                $row['created_at'],
            ]);
        }
        fclose($output);
        exit;
    }

    /**
     * 给代理充值额度
     */
    public function recharge(): void
    {
        $input = $this->getJsonInput();
        $validator = new Validator($input);
        if (!$validator->validate([
            'agent_id' => 'required|integer',
            'amount'   => 'required|numeric',
        ])) {
            $this->error($validator->getFirstError());
        }

        $agentId = (int) $input['agent_id'];
        $amount  = (float) $input['amount'];
        $remark  = $input['remark'] ?? '';

        if ($amount <= 0) {
            $this->error('额度必须大于0');
        }

        $db = Database::getInstance();

        // 验证代理存在
        $agent = $db->fetch(
            "SELECT u.id, a.id AS agent_record_id, COALESCE(a.total_quota, 0) AS total_quota,
                    COALESCE(a.used_quota, 0) AS used_quota
             FROM {$db->table('users')} u
             LEFT JOIN {$db->table('agents')} a ON u.id = a.user_id
             WHERE u.id = ? AND u.role = 'agent' AND u.deleted_at IS NULL",
            [$agentId]
        );
        if (!$agent) {
            $this->error('代理不存在');
        }

        $balanceBefore = (float) ($agent['total_quota'] ?? 0) - (float) ($agent['used_quota'] ?? 0);

        $db->beginTransaction();
        try {
            if ($agent['agent_record_id']) {
                $db->execute(
                    "UPDATE {$db->table('agents')} SET total_quota = total_quota + ? WHERE user_id = ?",
                    [$amount, $agentId]
                );
            } else {
                $db->insert(
                    "INSERT INTO {$db->table('agents')} (user_id, total_quota, used_quota) VALUES (?, ?, 0)",
                    [$agentId, $amount]
                );
            }

            $balanceAfter = $balanceBefore + $amount;

            // 记录额度变动
            $db->insert(
                "INSERT INTO {$db->table('agent_quota_logs')} (agent_id, change_type, amount, balance_before, balance_after, remark, operator_id, created_at)
                 VALUES (?, 'recharge', ?, ?, ?, ?, ?, NOW())",
                [$agentId, $amount, $balanceBefore, $balanceAfter, $remark, $this->getUserId()]
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            $this->error('充值失败: ' . $e->getMessage());
        }

        logger('agent_recharge', 'agent', $agentId, ['amount' => $amount]);

        $this->success([
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
        ], '充值成功');
    }

    /**
     * 调整代理额度
     */
    public function adjustQuota(): void
    {
        $input = $this->getJsonInput();
        $agentId = (int) ($input['agent_id'] ?? 0);
        $amount  = (float) ($input['amount'] ?? 0);
        $remark  = $input['remark'] ?? '';

        $db = Database::getInstance();

        $agent = $db->fetch(
            "SELECT a.* FROM {$db->table('agents')} a
             JOIN {$db->table('users')} u ON a.user_id = u.id
             WHERE a.user_id = ? AND u.role = 'agent'",
            [$agentId]
        );
        if (!$agent) {
            $this->error('代理不存在');
        }

        $balanceBefore = (float) $agent['total_quota'] - (float) $agent['used_quota'];
        $balanceAfter  = $balanceBefore + $amount;

        if ($balanceAfter < 0) {
            $this->error('调整后额度不能为负');
        }

        $db->beginTransaction();
        try {
            $db->execute(
                "UPDATE {$db->table('agents')} SET total_quota = total_quota + ? WHERE user_id = ?",
                [$amount, $agentId]
            );

            $db->insert(
                "INSERT INTO {$db->table('agent_quota_logs')} (agent_id, change_type, amount, balance_before, balance_after, remark, operator_id, created_at)
                 VALUES (?, 'adjust', ?, ?, ?, ?, ?, NOW())",
                [$agentId, $amount, $balanceBefore, $balanceAfter, $remark, $this->getUserId()]
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            $this->error('调整失败');
        }

        logger('agent_adjust_quota', 'agent', $agentId, ['amount' => $amount]);

        $this->success([
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
        ], '调整成功');
    }

    /**
     * 代理额度变动日志
     */
    public function quotaLogs(): void
    {
        $db = Database::getInstance();
        $page     = (int) ($_GET['page'] ?? 1);
        $pageSize = min((int) ($_GET['page_size'] ?? 20), 100);
        $agentId  = $_GET['agent_id'] ?? '';
        $offset   = ($page - 1) * $pageSize;

        $where = "WHERE 1=1";
        $params = [];

        if ($agentId) {
            $where .= " AND agent_id = ?";
            $params[] = $agentId;
        }

        $total = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('agent_quota_logs')} {$where}",
            $params
        );

        $logs = $db->fetchAll(
            "SELECT l.*, u.username AS agent_name, op.username AS operator_name
             FROM {$db->table('agent_quota_logs')} l
             LEFT JOIN {$db->table('users')} u ON l.agent_id = u.id
             LEFT JOIN {$db->table('users')} op ON l.operator_id = op.id
             {$where}
             ORDER BY l.id DESC
             LIMIT {$offset}, {$pageSize}",
            $params
        );

        $this->success([
            'list'      => $logs,
            'total'     => (int) $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 获取代理可用额度 (Agent自己查看)
     */
    public function myQuota(): void
    {
        $userId = $this->getUserId();
        $db = Database::getInstance();

        $agent = $db->fetch(
            "SELECT total_quota, used_quota, frozen_quota FROM {$db->table('agents')} WHERE user_id = ?",
            [$userId]
        );

        if (!$agent) {
            $this->success(['total_quota' => 0, 'used_quota' => 0, 'frozen_quota' => 0, 'remain_quota' => 0]);
            return;
        }

        $this->success([
            'total_quota'  => (float) $agent['total_quota'],
            'used_quota'   => (float) $agent['used_quota'],
            'frozen_quota' => (float) $agent['frozen_quota'],
            'remain_quota' => (float) $agent['total_quota'] - (float) $agent['used_quota'] - (float) $agent['frozen_quota'],
        ]);
    }

    /**
     * 代理为下级用户授权（消耗额度）
     */
    public function authorizeCard(): void
    {
        // 仅代理角色可使用此接口
        if ($this->getUserRole() !== 'agent') {
            $this->error('仅代理用户可执行此操作', 403);
        }

        $userId = $this->getUserId();
        $input  = $this->getJsonInput();

        $validator = new Validator($input);
        if (!$validator->validate([
            'project_id'   => 'required|integer',
            'card_type_id' => 'required|integer',
        ])) {
            $this->error($validator->getFirstError());
        }

        $projectId  = (int) $input['project_id'];
        $cardTypeId = (int) $input['card_type_id'];
        $bindInfo   = $input['bind_info'] ?? [];
        $remark     = $input['remark'] ?? '';

        $db = Database::getInstance();

        // 验证项目
        $project = $db->fetch("SELECT id FROM {$db->table('projects')} WHERE id = ? AND status = 1 AND deleted_at IS NULL", [$projectId]);
        if (!$project) {
            $this->error('项目不存在或已禁用');
        }

        // 验证卡密类型
        $cardType = $db->fetch(
            "SELECT * FROM {$db->table('card_types')} WHERE id = ? AND project_id = ? AND status = 1",
            [$cardTypeId, $projectId]
        );
        if (!$cardType) {
            $this->error('卡密类型不存在');
        }
        if ((float) $cardType['agent_cost'] <= 0) {
            $this->error('该套餐未配置代理价格，无法生成');
        }

        // 检查额度
        $agent = $db->fetch("SELECT * FROM {$db->table('agents')} WHERE user_id = ?", [$userId]);
        if (!$agent) {
            $this->error('代理额度账户不存在，请联系管理员充值');
        }
        $remainQuota = (float) ($agent['total_quota'] ?? 0) - (float) ($agent['used_quota'] ?? 0) - (float) ($agent['frozen_quota'] ?? 0);

        if ($remainQuota < (float) $cardType['agent_cost']) {
            $this->error('可用额度不足，请充值');
        }

        $snowflake = new \App\Services\SnowflakeService();
        $cardKey = $snowflake->generateCardKey();

        $expireTime = null;
        if ($cardType['duration_days'] > 0) {
            $expireTime = date('Y-m-d H:i:s', strtotime("+{$cardType['duration_days']} days"));
        }

        $db->beginTransaction();
        try {
            // 创建卡密
            $cardId = $db->insert(
                "INSERT INTO {$db->table('cards')} (card_key, project_id, card_type_id, type, duration_days, status, bind_info, bound_at, expire_time, use_user_id, remark)
                 VALUES (?, ?, ?, ?, ?, 'used', ?, NOW(), ?, ?, ?)",
                [
                    $cardKey,
                    $projectId,
                    $cardTypeId,
                    $cardType['name'],
                    $cardType['duration_days'],
                    json_encode($bindInfo, JSON_UNESCAPED_UNICODE),
                    $expireTime,
                    $userId,
                    $remark,
                ]
            );

            // 扣减额度
            $db->execute(
                "UPDATE {$db->table('agents')} SET used_quota = used_quota + ? WHERE user_id = ?",
                [(float) $cardType['agent_cost'], $userId]
            );

            $balanceBefore = $remainQuota;
            $balanceAfter  = $remainQuota - (float) $cardType['agent_cost'];

            // 记录额度变动
            $db->insert(
                "INSERT INTO {$db->table('agent_quota_logs')} (agent_id, change_type, amount, balance_before, balance_after, target_type, target_id, remark, operator_id, created_at)
                 VALUES (?, 'consume', ?, ?, ?, 'card', ?, ?, ?, NOW())",
                [$userId, (float) $cardType['agent_cost'], $balanceBefore, $balanceAfter, $cardId, $remark, $this->getUserId()]
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            $this->error('授权失败: ' . $e->getMessage());
        }

        logger('agent_authorize', 'card', $cardId, ['project_id' => $projectId, 'card_type' => $cardType['name']]);

        $this->success([
            'card_id'  => $cardId,
            'card_key' => $cardKey,
            'expire_time' => $expireTime,
        ], '授权成功');
    }

    /**
     * 重置代理密码
     * POST /api/agents/{id}/reset-password
     */
    public function resetPassword(int $agentId): void
    {
        if ($this->getUserRole() !== 'admin') {
            $this->error('权限不足', 403);
        }

        $db = Database::getInstance();
        $agent = $db->fetch(
            "SELECT id, username FROM {$db->table('users')} WHERE id = ? AND role = 'agent' AND deleted_at IS NULL",
            [$agentId]
        );
        if (!$agent) {
            $this->error('代理不存在');
        }

        $newPassword = substr(bin2hex(random_bytes(4)), 0, 8);
        $db->execute(
            "UPDATE {$db->table('users')} SET password = ? WHERE id = ?",
            [passwordHash($newPassword), $agentId]
        );

        logger('reset_agent_password', 'user', $agentId, ['username' => $agent['username']]);

        $this->success(['password' => $newPassword], '密码已重置');
    }
}