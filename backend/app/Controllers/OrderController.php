<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;

/**
 * 订单管理控制器
 */
class OrderController extends Controller
{
    /**
     * 订单列表
     */
    /**
     * 订单列表
     * GET /api/orders
     * Query: status, pay_type, project_id, keyword, start_date, end_date, sort_by, sort_order, page, page_size
     */
    public function list(): void
    {
        $db = Database::getInstance();
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize   = min((int) ($_GET['page_size'] ?? 20), 100);
        $offset     = ($page - 1) * $pageSize;
        $status     = $_GET['status'] ?? '';
        $payType    = $_GET['pay_type'] ?? '';
        $projectId  = $_GET['project_id'] ?? '';
        $keyword    = trim($_GET['keyword'] ?? '');
        $startDate  = $_GET['start_date'] ?? '';
        $endDate    = $_GET['end_date'] ?? '';
        $sortBy     = in_array($_GET['sort_by'] ?? '', ['id', 'amount', 'status', 'pay_type', 'paid_at', 'created_at']) ? $_GET['sort_by'] : 'id';
        $sortOrder  = in_array(strtolower($_GET['sort_order'] ?? ''), ['asc', 'desc']) ? strtoupper($_GET['sort_order']) : 'DESC';

        $where = "WHERE 1=1";
        $params = [];

        if ($status) {
            $where .= " AND o.status = ?";
            $params[] = $status;
        }
        if ($payType) {
            $where .= " AND o.pay_type = ?";
            $params[] = $payType;
        }
        if ($projectId) {
            $where .= " AND o.project_id = ?";
            $params[] = $projectId;
        }
        if ($keyword) {
            $where .= " AND (o.order_no LIKE ? OR o.trade_no LIKE ? OR c.card_key LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        if ($startDate) {
            $where .= " AND DATE(o.created_at) >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $where .= " AND DATE(o.created_at) <= ?";
            $params[] = $endDate;
        }

        $total = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('orders')} o {$where}",
            $params
        );

        $orders = $db->fetchAll(
            "SELECT o.*, p.name AS project_name, ct.name AS card_type_name
             FROM {$db->table('orders')} o
             LEFT JOIN {$db->table('projects')} p ON o.project_id = p.id
             LEFT JOIN {$db->table('card_types')} ct ON o.card_type_id = ct.id
             {$where}
             ORDER BY o.{$sortBy} {$sortOrder}
             LIMIT {$offset}, {$pageSize}",
            $params
        );

        $this->success([
            'list'      => $orders,
            'total'     => (int) $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 订单详情
     */
    public function detail(int $id): void
    {
        $db = Database::getInstance();
        $order = $db->fetch(
            "SELECT o.*, p.name AS project_name, ct.name AS card_type_name, c.card_key
             FROM {$db->table('orders')} o
             LEFT JOIN {$db->table('projects')} p ON o.project_id = p.id
             LEFT JOIN {$db->table('card_types')} ct ON o.card_type_id = ct.id
             LEFT JOIN {$db->table('cards')} c ON o.card_id = c.id
             WHERE o.id = ?",
            [$id]
        );
        if (!$order) {
            $this->error('订单不存在', 404);
        }
        $this->success($order);
    }

    /**
     * 手动补单 (Admin操作)
     */
    public function manualComplete(int $id): void
    {
        if ($this->getUserRole() !== 'admin') {
            $this->error('仅管理员可执行此操作', 403);
        }

        $db = Database::getInstance();
        $order = $db->fetch("SELECT * FROM {$db->table('orders')} WHERE id = ?", [$id]);
        if (!$order) {
            $this->error('订单不存在');
        }
        if ($order['status'] !== 'pending') {
            $this->error('订单状态不允许补单');
        }

        $this->processOrderPaid($order['id']);
        logger('manual_complete_order', 'order', $id);

        $this->success(null, '补单成功');
    }

    /**
     * 导出订单
     * GET /api/orders/export
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
                $where .= ' AND o.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = array_merge($params, $ids);
            }
        }

        $rows = $db->fetchAll(
            "SELECT o.*, p.name AS project_name, ct.name AS card_type_name, c.card_key
             FROM {$db->table('orders')} o
             LEFT JOIN {$db->table('projects')} p ON o.project_id = p.id
             LEFT JOIN {$db->table('card_types')} ct ON o.card_type_id = ct.id
             LEFT JOIN {$db->table('cards')} c ON o.card_id = c.id
             {$where}
             ORDER BY o.id DESC
             LIMIT 50000",
            $params
        );

        $payTypeMap = ['alipay' => '支付宝', 'wxpay' => '微信', 'qqpay' => 'QQ钱包'];
        $statusMap  = ['pending' => '待支付', 'paid' => '已支付', 'expired' => '已过期', 'refunded' => '已退款'];

        if ($format === 'excel') {
            $filename = 'orders_export_' . date('YmdHis') . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache');
            $html = '<table border="1"><tr><th>订单号</th><th>项目</th><th>套餐</th><th>金额</th><th>支付方式</th><th>状态</th><th>卡密</th><th>支付时间</th><th>创建时间</th></tr>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($row['order_no']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['project_name'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['card_type_name'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['amount']) . '</td>';
                $html .= '<td>' . htmlspecialchars($payTypeMap[$row['pay_type']] ?? ($row['pay_type'] ?: '-')) . '</td>';
                $html .= '<td>' . htmlspecialchars($statusMap[$row['status']] ?? $row['status']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['card_key'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['paid_at'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            echo $html;
            exit;
        }

        $filename = 'orders_export_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['订单号', '项目', '套餐', '金额', '支付方式', '状态', '卡密', '支付时间', '创建时间']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['order_no'],
                $row['project_name'] ?? '',
                $row['card_type_name'] ?? '',
                $row['amount'],
                $payTypeMap[$row['pay_type']] ?? ($row['pay_type'] ?: '-'),
                $statusMap[$row['status']] ?? $row['status'],
                $row['card_key'] ?? '',
                $row['paid_at'] ?? '',
                $row['created_at'],
            ]);
        }
        fclose($output);
        exit;
    }

    /**
     * 处理支付成功
     */
    public function processOrderPaid(int $orderId): void
    {
        $db = Database::getInstance();
        $order = $db->fetch("SELECT * FROM {$db->table('orders')} WHERE id = ?", [$orderId]);
        if (!$order || $order['status'] !== 'pending') return;

        $snowflake = new \App\Services\SnowflakeService();
        $cardKey = $snowflake->generateCardKey();

        $cardType = $db->fetch(
            "SELECT * FROM {$db->table('card_types')} WHERE id = ?",
            [$order['card_type_id']]
        );

        $expireTime = null;
        $durationDays = $cardType['duration_days'] ?? 0;
        if ($durationDays > 0) {
            $expireTime = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));
        }

        $db->beginTransaction();
        try {
            // 创建卡密
            $cardId = $db->insert(
                "INSERT INTO {$db->table('cards')} (card_key, project_id, card_type_id, type, duration_days, status, expire_time, order_id, remark)
                 VALUES (?, ?, ?, ?, ?, 'unused', ?, ?, '在线购买')",
                [
                    $cardKey,
                    $order['project_id'],
                    $order['card_type_id'],
                    $cardType['name'] ?? '',
                    $durationDays,
                    $expireTime,
                    $orderId,
                ]
            );

            // 更新订单
            $db->execute(
                "UPDATE {$db->table('orders')} SET status = 'paid', card_id = ?, paid_at = NOW() WHERE id = ?",
                [$cardId, $orderId]
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
}