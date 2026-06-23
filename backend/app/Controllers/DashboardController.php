<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * Dashboard与统计控制器
 */
class DashboardController extends Controller
{
    /**
     * 首页Dashboard数据
     */
    public function index(): void
    {
        $db = Database::getInstance();
        $userRole = $this->getUserRole();
        $userId   = $this->getUserId();

        $data = [];

        if ($userRole === 'admin') {
            // 项目总数
            $data['total_projects'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM {$db->table('projects')} WHERE deleted_at IS NULL"
            );
            // 用户总数
            $data['total_users'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM {$db->table('users')} WHERE deleted_at IS NULL"
            );
            // 卡密总数
            $data['total_cards'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM {$db->table('cards')}"
            );
            // 已使用卡密
            $data['used_cards'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM {$db->table('cards')} WHERE status = 'used'"
            );
            // 订单总数
            $data['total_orders'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM {$db->table('orders')}"
            );
            // 已支付订单
            $data['paid_orders'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM {$db->table('orders')} WHERE status = 'paid'"
            );
            // 总收入
            $data['total_revenue'] = (float) $db->fetchColumn(
                "SELECT COALESCE(SUM(amount), 0) FROM {$db->table('orders')} WHERE status = 'paid'"
            );
            // 今日收入
            $data['today_revenue'] = (float) $db->fetchColumn(
                "SELECT COALESCE(SUM(amount), 0) FROM {$db->table('orders')} WHERE status = 'paid' AND DATE(paid_at) = CURDATE()"
            );
            // 代理总数
            $data['total_agents'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM {$db->table('users')} WHERE role = 'agent' AND deleted_at IS NULL"
            );
            // 近7天订单趋势
            $data['order_trend'] = $db->fetchAll(
                "SELECT DATE(created_at) AS date, COUNT(*) AS count, COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END), 0) AS amount
                 FROM {$db->table('orders')}
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY date ASC"
            );
        } elseif ($userRole === 'agent') {
            // 代理数据
            $agent = $db->fetch(
                "SELECT total_quota, used_quota, frozen_quota FROM {$db->table('agents')} WHERE user_id = ?",
                [$userId]
            );
            $data['agent_quota'] = $agent ?: ['total_quota' => 0, 'used_quota' => 0, 'frozen_quota' => 0];
            $data['remain_quota'] = (float) ($data['agent_quota']['total_quota'] ?? 0) - (float) ($data['agent_quota']['used_quota'] ?? 0);

            // 代理生成的卡密总数
            $data['total_authorized'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM {$db->table('cards')} WHERE use_user_id = ?",
                [$userId]
            );
            // 今日生成卡密数
            $data['today_authorized'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM {$db->table('cards')} WHERE use_user_id = ? AND DATE(created_at) = CURDATE()",
                [$userId]
            );
        }

        $this->success($data);
    }

    /**
     * 操作日志列表
     * GET /api/logs
     * Query: action, user_id, username, start_date, end_date, sort_by, sort_order, page, page_size
     */
    public function logs(): void
    {
        $db = Database::getInstance();
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize   = min((int) ($_GET['page_size'] ?? 20), 100);
        $offset     = ($page - 1) * $pageSize;
        $action     = $_GET['action'] ?? '';
        $userId     = $_GET['user_id'] ?? '';
        $username   = trim($_GET['username'] ?? '');
        $startDate  = $_GET['start_date'] ?? '';
        $endDate    = $_GET['end_date'] ?? '';
        $sortBy     = in_array($_GET['sort_by'] ?? '', ['id', 'user_id', 'action', 'target_type', 'target_id', 'created_at']) ? $_GET['sort_by'] : 'id';
        $sortOrder  = in_array(strtolower($_GET['sort_order'] ?? ''), ['asc', 'desc']) ? strtoupper($_GET['sort_order']) : 'DESC';

        $where = "WHERE 1=1";
        $params = [];

        if ($action) {
            $where .= " AND l.action = ?";
            $params[] = $action;
        }
        if ($userId) {
            $where .= " AND l.user_id = ?";
            $params[] = $userId;
        }
        if ($username) {
            $where .= " AND (u.username LIKE ? OR u.nickname LIKE ?)";
            $params[] = "%{$username}%";
            $params[] = "%{$username}%";
        }
        if ($startDate) {
            $where .= " AND DATE(l.created_at) >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $where .= " AND DATE(l.created_at) <= ?";
            $params[] = $endDate;
        }

        $total = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('logs')} l LEFT JOIN {$db->table('users')} u ON l.user_id = u.id {$where}",
            $params
        );

        $logs = $db->fetchAll(
            "SELECT l.*, u.username, u.nickname
             FROM {$db->table('logs')} l
             LEFT JOIN {$db->table('users')} u ON l.user_id = u.id
             {$where}
             ORDER BY l.{$sortBy} {$sortOrder}
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
     * 导出日志
     * GET /api/logs/export
     * Query: ids, format(csv|excel)
     */
    public function exportLogs(): void
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
                $where .= ' AND l.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = array_merge($params, $ids);
            }
        }

        $rows = $db->fetchAll(
            "SELECT l.*, u.username, u.nickname
             FROM {$db->table('logs')} l
             LEFT JOIN {$db->table('users')} u ON l.user_id = u.id
             {$where}
             ORDER BY l.id DESC
             LIMIT 50000",
            $params
        );

        if ($format === 'excel') {
            $filename = 'logs_export_' . date('YmdHis') . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache');
            $html = '<table border="1"><tr><th>ID</th><th>用户</th><th>昵称</th><th>操作类型</th><th>目标类型</th><th>目标ID</th><th>详情</th><th>IP</th><th>时间</th></tr>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($row['id']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['username'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['nickname'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['action']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['target_type']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['target_id']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['detail'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['ip'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            echo $html;
            exit;
        }

        $filename = 'logs_export_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['ID', '用户', '昵称', '操作类型', '目标类型', '目标ID', '详情', 'IP', '时间']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['username'] ?? '',
                $row['nickname'] ?? '',
                $row['action'],
                $row['target_type'],
                $row['target_id'],
                $row['detail'] ?? '',
                $row['ip'] ?? '',
                $row['created_at'],
            ]);
        }
        fclose($output);
        exit;
    }
}