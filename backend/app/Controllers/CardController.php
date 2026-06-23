<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Services\PermissionService;
use App\Services\SnowflakeService;

/**
 * 卡密管理控制器
 */
class CardController extends Controller
{
    /**
     * 卡密列表
     */
    /**
     * 卡密列表
     * GET /api/cards
     * Query: project_id, card_type_id, status, keyword, start_date, end_date, sort_by, sort_order, page, page_size
     */
    public function list(): void
    {
        $db = Database::getInstance();
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize   = min((int) ($_GET['page_size'] ?? 20), 100);
        $offset     = ($page - 1) * $pageSize;
        $projectId  = $_GET['project_id'] ?? '';
        $status     = $_GET['status'] ?? '';
        $cardTypeId = $_GET['card_type_id'] ?? '';
        $keyword    = trim($_GET['keyword'] ?? '');
        $startDate  = $_GET['start_date'] ?? '';
        $endDate    = $_GET['end_date'] ?? '';
        $sortBy     = in_array($_GET['sort_by'] ?? '', ['id', 'card_key', 'project_id', 'card_type_id', 'status', 'duration_days', 'expire_time', 'bound_at', 'created_at']) ? $_GET['sort_by'] : 'id';
        $sortOrder  = in_array(strtolower($_GET['sort_order'] ?? ''), ['asc', 'desc']) ? strtoupper($_GET['sort_order']) : 'DESC';

        $where = "WHERE 1=1";
        $params = [];

        // 数据范围权限过滤（管理员查看全部，代理仅查看自身及下级代理生成的卡密）
        $scope = PermissionService::cardScope($this->getCurrentUser());
        $where .= $scope['where'];
        $params = array_merge($params, $scope['params']);

        if ($projectId) {
            $where .= " AND c.project_id = ?";
            $params[] = $projectId;
        }
        if ($status) {
            $where .= " AND c.status = ?";
            $params[] = $status;
        }
        if ($cardTypeId) {
            $where .= " AND c.card_type_id = ?";
            $params[] = $cardTypeId;
        }
        if ($keyword) {
            $where .= " AND c.card_key LIKE ?";
            $params[] = "%{$keyword}%";
        }
        if ($startDate) {
            $where .= " AND DATE(c.created_at) >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $where .= " AND DATE(c.created_at) <= ?";
            $params[] = $endDate;
        }

        $total = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('cards')} c {$where}",
            $params
        );

        $cards = $db->fetchAll(
            "SELECT c.*, p.name AS project_name, ct.name AS card_type_name,
                    a.bot_qq, a.contact_qq, a.contact_name
             FROM {$db->table('cards')} c
             LEFT JOIN {$db->table('projects')} p ON c.project_id = p.id
             LEFT JOIN {$db->table('card_types')} ct ON c.card_type_id = ct.id
             LEFT JOIN {$db->table('authorizations')} a ON c.id = a.card_id
             {$where}
             ORDER BY c.{$sortBy} {$sortOrder}
             LIMIT {$offset}, {$pageSize}",
            $params
        );

        $this->success([
            'list'      => $cards,
            'total'     => (int) $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 批量生成卡密
     */
    public function generate(): void
    {
        $input = $this->getJsonInput();
        $validator = new Validator($input);
        if (!$validator->validate([
            'project_id'   => 'required|integer',
            'card_type_id' => 'required|integer',
            'count'        => 'required|integer',
        ])) {
            $this->error($validator->getFirstError());
        }

        $projectId  = (int) $input['project_id'];
        $cardTypeId = (int) $input['card_type_id'];
        $count      = min((int) $input['count'], 10000); // 单次最多10000张
        $remark     = $input['remark'] ?? '';

        $db = Database::getInstance();

        // 验证项目存在
        $project = $db->fetch("SELECT id FROM {$db->table('projects')} WHERE id = ? AND deleted_at IS NULL", [$projectId]);
        if (!$project) {
            $this->error('项目不存在');
        }

        // 验证卡密类型
        $cardType = $db->fetch(
            "SELECT * FROM {$db->table('card_types')} WHERE id = ? AND project_id = ?",
            [$cardTypeId, $projectId]
        );
        if (!$cardType) {
            $this->error('卡密类型不存在');
        }

        $userId = $this->getUserId();
        $userRole = $this->getUserRole();

        // 项目管理员：只能在自己负责的项目中生成卡密
        if ($userRole === 'project_admin') {
            $currentUser = $this->getCurrentUser();
            $projectIds = $currentUser['project_ids'] ?? [];
            if (!in_array($projectId, $projectIds)) {
                $this->error('无权在此项目中生成卡密', 403);
            }
        }

        // 代理身份：验证余额并扣费
        $needDeduct = false;
        $deductAmount = 0;
        $quotaBefore = 0;

        if ($userRole === 'agent') {
            if ((float) $cardType['agent_cost'] <= 0) {
                $this->error('该套餐未配置代理价格，无法生成');
            }

            $agent = $db->fetch("SELECT * FROM {$db->table('agents')} WHERE user_id = ?", [$userId]);
            if (!$agent) {
                $this->error('代理额度账户不存在，请联系管理员充值');
            }

            $remainQuota = (float) ($agent['total_quota'] ?? 0) - (float) ($agent['used_quota'] ?? 0) - (float) ($agent['frozen_quota'] ?? 0);
            $totalCost = (float) $cardType['agent_cost'] * $count;

            if ($remainQuota < $totalCost) {
                $this->error("可用额度不足，需要 ¥{$totalCost}，当前剩余 ¥{$remainQuota}");
            }

            $needDeduct = true;
            $deductAmount = $totalCost;
            $quotaBefore = $remainQuota;
        }

        $snowflake = new SnowflakeService();
        $cards = [];

        $db->beginTransaction();
        try {
            for ($i = 0; $i < $count; $i++) {
                $cardKey = $snowflake->generateCardKey();
                $db->insert(
                    "INSERT INTO {$db->table('cards')} (card_key, project_id, card_type_id, type, duration_days, status, use_user_id, remark) VALUES (?, ?, ?, ?, ?, 'unused', ?, ?)",
                    [$cardKey, $projectId, $cardTypeId, $cardType['name'], $cardType['duration_days'], $userId, $remark]
                );
                $cards[] = $cardKey;
            }

            // 代理扣费
            if ($needDeduct) {
                $db->execute(
                    "UPDATE {$db->table('agents')} SET used_quota = used_quota + ? WHERE user_id = ?",
                    [$deductAmount, $userId]
                );

                $quotaAfter = $quotaBefore - $deductAmount;

                // 记录额度变动
                $db->insert(
                    "INSERT INTO {$db->table('agent_quota_logs')} (agent_id, change_type, amount, balance_before, balance_after, target_type, target_id, remark, operator_id, created_at)
                     VALUES (?, 'consume', ?, ?, ?, 'card_generate', ?, ?, ?, NOW())",
                    [$userId, $deductAmount, $quotaBefore, $quotaAfter, $count, "生成{$count}张卡密", $this->getUserId()]
                );
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            $this->error('生成卡密失败: ' . $e->getMessage());
        }

        logger('generate_cards', 'card', 0, [
            'count'      => $count,
            'project_id' => $projectId,
            'card_type_id' => $cardTypeId,
            'role'       => $this->getUserRole(),
        ]);

        $this->success([
            'count' => $count,
            'cards' => $cards,
        ], "成功生成{$count}张卡密");
    }

    /**
     * 导出卡密
     * GET /api/cards/export
     * Query: ids, format(csv|excel), project_id, status, card_type_id
     */
    public function export(): void
    {
        $projectId  = $_GET['project_id'] ?? '';
        $status     = $_GET['status'] ?? '';
        $cardTypeId = $_GET['card_type_id'] ?? '';
        $ids        = $_GET['ids'] ?? [];
        $format     = strtolower($_GET['format'] ?? 'csv');
        if (!in_array($format, ['csv', 'excel'])) {
            $format = 'csv';
        }

        $db = Database::getInstance();
        $where = "WHERE 1=1";
        $params = [];

        if (!empty($ids)) {
            $ids = is_array($ids) ? $ids : explode(',', $ids);
            $ids = array_filter(array_map('intval', $ids));
            if (!empty($ids)) {
                $where .= " AND c.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")";
                $params = array_merge($params, $ids);
            }
        }
        if ($projectId) {
            $where .= " AND c.project_id = ?";
            $params[] = $projectId;
        }
        if ($status) {
            $where .= " AND c.status = ?";
            $params[] = $status;
        }
        if ($cardTypeId) {
            $where .= " AND c.card_type_id = ?";
            $params[] = $cardTypeId;
        }

        $cards = $db->fetchAll(
            "SELECT c.card_key, c.type, c.status, c.duration_days, c.expire_time, c.bound_at, c.created_at,
                    p.name AS project_name
             FROM {$db->table('cards')} c
             LEFT JOIN {$db->table('projects')} p ON c.project_id = p.id
             {$where}
             ORDER BY c.id DESC
             LIMIT 50000",
            $params
        );

        if ($format === 'excel') {
            $this->exportExcel($cards);
        } else {
            $this->exportCsv($cards);
        }
    }

    /**
     * 导出CSV
     */
    private function exportCsv(array $cards): never
    {
        $filename = 'cards_export_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['卡密', '项目', '类型', '有效天数', '状态', '绑定时间', '过期时间', '生成时间']);

        $statusMap = ['unused' => '未使用', 'used' => '已使用', 'disabled' => '已禁用'];
        foreach ($cards as $card) {
            fputcsv($output, [
                $card['card_key'],
                $card['project_name'] ?? '',
                $card['type'],
                $card['duration_days'] == 0 ? '永久' : $card['duration_days'] . '天',
                $statusMap[$card['status']] ?? $card['status'],
                $card['bound_at'] ?? '',
                $card['expire_time'] ?? '',
                $card['created_at'],
            ]);
        }
        fclose($output);
        exit;
    }

    /**
     * 导出Excel (HTML表格伪Excel)
     */
    private function exportExcel(array $cards): never
    {
        $filename = 'cards_export_' . date('YmdHis') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $statusMap = ['unused' => '未使用', 'used' => '已使用', 'disabled' => '已禁用'];
        $html = '<table border="1"><tr><th>卡密</th><th>项目</th><th>类型</th><th>有效天数</th><th>状态</th><th>绑定时间</th><th>过期时间</th><th>生成时间</th></tr>';
        foreach ($cards as $card) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($card['card_key']) . '</td>';
            $html .= '<td>' . htmlspecialchars($card['project_name'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($card['type']) . '</td>';
            $html .= '<td>' . htmlspecialchars($card['duration_days'] == 0 ? '永久' : $card['duration_days'] . '天') . '</td>';
            $html .= '<td>' . htmlspecialchars($statusMap[$card['status']] ?? $card['status']) . '</td>';
            $html .= '<td>' . htmlspecialchars($card['bound_at'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($card['expire_time'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($card['created_at']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        echo $html;
        exit;
    }

    /**
     * 导入卡密
     * POST /api/cards/import
     * Body: multipart/form-data { file, project_id }
     */
    public function import(): void
    {
        if (!isset($_FILES['file'])) {
            $this->error('请上传文件');
        }

        $projectId  = (int) ($_POST['project_id'] ?? 0);
        $cardTypeId = (int) ($_POST['card_type_id'] ?? 0);
        if (!$projectId) {
            $this->error('请选择项目');
        }
        if (!$cardTypeId) {
            $this->error('请选择套餐');
        }

        $db = Database::getInstance();
        $userId   = $this->getUserId();
        $userRole = $this->getUserRole();

        // 项目管理员：只能在自己负责的项目中导入卡密
        if ($userRole === 'project_admin') {
            $currentUser = $this->getCurrentUser();
            $projectIds = $currentUser['project_ids'] ?? [];
            if (!in_array($projectId, $projectIds)) {
                $this->error('无权在此项目中导入卡密', 403);
            }
        }

        $project = $db->fetch("SELECT id FROM {$db->table('projects')} WHERE id = ? AND deleted_at IS NULL", [$projectId]);
        if (!$project) {
            $this->error('项目不存在');
        }

        $cardType = $db->fetch(
            "SELECT * FROM {$db->table('card_types')} WHERE id = ? AND project_id = ?",
            [$cardTypeId, $projectId]
        );
        if (!$cardType) {
            $this->error('套餐不存在或不属于该项目');
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->error('文件上传失败');
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            $this->error('文件大小不能超过10MB');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
            $this->error('仅支持 CSV、Excel 格式');
        }

        // 先读取所有行，统计数量
        $rows = [];
        if ($ext === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                $this->error('无法读取文件');
            }
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
            $header = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                $cardKey = trim($row[0] ?? '');
                if ($cardKey) {
                    $rows[] = $cardKey;
                }
            }
            fclose($handle);
        } else {
            $this->error('Excel 解析暂不支持，请上传 CSV 文件');
        }

        $count = count($rows);
        if ($count === 0) {
            $this->error('文件中没有有效的卡密数据');
        }

        // 代理身份：验证余额并扣费
        $needDeduct = false;
        $deductAmount = 0;
        $quotaBefore = 0;

        if ($userRole === 'agent') {
            if ((float) $cardType['agent_cost'] <= 0) {
                $this->error('该套餐未配置代理价格，无法导入');
            }

            $agent = $db->fetch("SELECT * FROM {$db->table('agents')} WHERE user_id = ?", [$userId]);
            if (!$agent) {
                $this->error('代理额度账户不存在，请联系管理员充值');
            }

            $remainQuota = (float) ($agent['total_quota'] ?? 0) - (float) ($agent['used_quota'] ?? 0) - (float) ($agent['frozen_quota'] ?? 0);
            $totalCost = (float) $cardType['agent_cost'] * $count;

            if ($remainQuota < $totalCost) {
                $this->error("可用额度不足，需要 ¥{$totalCost}，当前剩余 ¥{$remainQuota}");
            }

            $needDeduct = true;
            $deductAmount = $totalCost;
            $quotaBefore = $remainQuota;
        }

        $snowflake = new SnowflakeService();

        $db->beginTransaction();
        try {
            foreach ($rows as $cardKey) {
                $db->insert(
                    "INSERT INTO {$db->table('cards')} (card_key, project_id, card_type_id, type, duration_days, status, use_user_id, created_at) VALUES (?, ?, ?, ?, ?, 'unused', ?, NOW())",
                    [$cardKey, $projectId, $cardTypeId, $cardType['name'], $cardType['duration_days'], $userId]
                );
            }

            // 代理扣费
            if ($needDeduct) {
                $db->execute(
                    "UPDATE {$db->table('agents')} SET used_quota = used_quota + ? WHERE user_id = ?",
                    [$deductAmount, $userId]
                );

                $quotaAfter = $quotaBefore - $deductAmount;
                $db->insert(
                    "INSERT INTO {$db->table('agent_quota_logs')} (agent_id, change_type, amount, balance_before, balance_after, target_type, target_id, remark, operator_id, created_at)
                     VALUES (?, 'deduct', ?, ?, ?, 'card_import', 0, ?, ?, NOW())",
                    [$agent['id'], $deductAmount, $quotaBefore, $quotaAfter, "导入{$count}张卡密", $userId]
                );
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            $this->error('导入失败: ' . $e->getMessage());
        }

        logger('import_cards', 'card', $projectId, ['count' => $count]);

        $this->success([
            'success_count' => $count,
            'error_count'   => 0,
            'errors'        => [],
        ], "成功导入 {$count} 张卡密");
    }

    /**
     * 禁用/启用卡密
     */
    public function toggleStatus(int $id): void
    {
        $input = $this->getJsonInput();
        $status = $input['status'] ?? '';

        if (!in_array($status, ['unused', 'disabled'])) {
            $this->error('无效的状态值');
        }

        $db = Database::getInstance();
        $card = $db->fetch("SELECT id, status FROM {$db->table('cards')} WHERE id = ?", [$id]);
        if (!$card) {
            $this->error('卡密不存在');
        }

        if ($card['status'] === 'used') {
            $this->error('已使用的卡密不能修改状态');
        }

        $db->execute("UPDATE {$db->table('cards')} SET status = ? WHERE id = ?", [$status, $id]);

        logger('toggle_card_status', 'card', $id, ['status' => $status]);

        $this->success(null, '操作成功');
    }

    /**
     * 卡密详情
     */
    public function detail(int $id): void
    {
        $db = Database::getInstance();
        $card = $db->fetch(
            "SELECT c.*, p.name AS project_name, ct.name AS card_type_name,
                    a.bot_qq, a.contact_qq, a.contact_name
             FROM {$db->table('cards')} c
             LEFT JOIN {$db->table('projects')} p ON c.project_id = p.id
             LEFT JOIN {$db->table('card_types')} ct ON c.card_type_id = ct.id
             LEFT JOIN {$db->table('authorizations')} a ON c.id = a.card_id
             WHERE c.id = ?",
            [$id]
        );
        if (!$card) {
            $this->error('卡密不存在', 404);
        }

        // 数据范围权限检查
        $scope = PermissionService::cardScope($this->getCurrentUser());
        if ($scope['where'] && !in_array((int) $card['use_user_id'], $scope['params'], true)) {
            $this->error('无权查看该卡密', 403);
        }

        $card['bind_info'] = json_decode($card['bind_info'] ?? '{}', true);
        $this->success($card);
    }

    /**
     * 批量更新卡密状态
     * POST /api/cards/batch-status
     * Body: { ids: [int], status: 'unused'|'disabled' }
     */
    public function batchStatus(): void
    {
        $input = $this->getJsonInput();
        $ids    = $input['ids'] ?? [];
        $status = $input['status'] ?? '';

        if (!is_array($ids) || empty($ids)) {
            $this->error('请选择要更新的卡密');
        }
        if (!in_array($status, ['unused', 'disabled'], true)) {
            $this->error('无效的状态值，只能是 unused 或 disabled');
        }

        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            $this->error('无效的卡密ID');
        }

        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 检查是否有已使用的卡密
        $usedCount = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('cards')} WHERE id IN ({$placeholders}) AND status = 'used'",
            $ids
        );
        if ($usedCount > 0) {
            $this->error('已使用的卡密不能修改状态');
        }

        $db->execute(
            "UPDATE {$db->table('cards')} SET status = ? WHERE id IN ({$placeholders})",
            array_merge([$status], $ids)
        );

        logger('batch_toggle_card_status', 'card', 0, ['count' => count($ids), 'status' => $status]);

        $this->success(['count' => count($ids)], '批量状态更新成功');
    }

    /**
     * 批量删除卡密
     * POST /api/cards/batch-delete
     * Body: { ids: [int] }
     */
    public function batchDelete(): void
    {
        $input = $this->getJsonInput();
        $ids = $input['ids'] ?? [];

        if (!is_array($ids) || empty($ids)) {
            $this->error('请选择要删除的卡密');
        }

        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            $this->error('无效的卡密ID');
        }

        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 删除前记录日志
        logger('batch_delete_cards', 'card', 0, ['count' => count($ids)]);

        $db->execute(
            "DELETE FROM {$db->table('cards')} WHERE id IN ({$placeholders})",
            $ids
        );

        $this->success(['count' => count($ids)], '批量删除成功');
    }

    /**
     * 卡密统计
     * GET /api/cards/stats
     */
    public function stats(): void
    {
        $db = Database::getInstance();
        $projectId = $_GET['project_id'] ?? '';

        $where = '';
        $params = [];
        if ($projectId) {
            $where = "WHERE project_id = ?";
            $params[] = $projectId;
        }

        $stats = $db->fetchAll(
            "SELECT status, COUNT(*) AS count FROM {$db->table('cards')} {$where} GROUP BY status",
            $params
        );

        $result = ['unused' => 0, 'used' => 0, 'disabled' => 0, 'total' => 0];
        foreach ($stats as $row) {
            $result[$row['status']] = (int) $row['count'];
            $result['total'] += (int) $row['count'];
        }

        $this->success($result);
    }
}