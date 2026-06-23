<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;

/**
 * 优惠码管理控制器
 */
class CouponController extends Controller
{
    /**
     * 优惠码列表
     * GET /api/coupons
     * Query: keyword, status, project_id, sort_by, sort_order, page, page_size
     */
    public function list(): void
    {
        $db = Database::getInstance();
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize   = min((int) ($_GET['page_size'] ?? 20), 100);
        $offset     = ($page - 1) * $pageSize;
        $keyword    = trim($_GET['keyword'] ?? '');
        $status     = $_GET['status'] ?? '';
        $projectId  = $_GET['project_id'] ?? '';
        $sortBy     = in_array($_GET['sort_by'] ?? '', ['id', 'code', 'name', 'discount_percent', 'used_count', 'max_use_count', 'min_amount', 'status', 'expire_at', 'created_at']) ? $_GET['sort_by'] : 'id';
        $sortOrder  = in_array(strtolower($_GET['sort_order'] ?? ''), ['asc', 'desc']) ? strtoupper($_GET['sort_order']) : 'DESC';

        $where = 'WHERE 1=1';
        $params = [];

        if ($keyword) {
            $where .= ' AND (code LIKE ? OR name LIKE ?)';
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        if ($status !== '') {
            $where .= ' AND status = ?';
            $params[] = (int) $status;
        }
        if ($projectId !== '') {
            $where .= ' AND (project_ids = ? OR project_ids LIKE ? OR project_ids LIKE ? OR project_ids = "")';
            $params[] = (string) $projectId;
            $params[] = "{$projectId},%";
            $params[] = "%,{$projectId}";
        }

        $total = $db->fetchColumn("SELECT COUNT(*) FROM {$db->table('coupons')} {$where}", $params);
        $rows = $db->fetchAll(
            "SELECT * FROM {$db->table('coupons')} {$where} ORDER BY {$sortBy} {$sortOrder} LIMIT {$offset}, {$pageSize}",
            $params
        );

        $this->success([
            'list' => $rows,
            'total' => (int) $total,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 创建优惠码
     * POST /api/coupons
     */
    public function create(): void
    {
        $input = $this->getJsonInput();
        $validator = new Validator($input);
        if (!$validator->validate(['code' => 'required|min:2|max:32', 'discount_percent' => 'required|numeric'])) {
            $this->error($validator->getFirstError());
        }

        $code = strtoupper(trim($input['code']));
        $discountPercent = (float) $input['discount_percent'];

        if ($discountPercent <= 0 || $discountPercent > 100) {
            $this->error('折扣百分比必须在1-100之间');
        }

        $db = Database::getInstance();
        $exists = $db->fetchColumn("SELECT id FROM {$db->table('coupons')} WHERE code = ?", [$code]);
        if ($exists) {
            $this->error('优惠码已存在');
        }

        $id = $db->insert(
            "INSERT INTO {$db->table('coupons')} (code, name, discount_percent, max_use_count, min_amount, project_ids, status, expire_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $code,
                $input['name'] ?? '',
                $discountPercent,
                (int) ($input['max_use_count'] ?? 0),
                (float) ($input['min_amount'] ?? 0),
                $input['project_ids'] ?? '',
                (int) ($input['status'] ?? 1),
                $input['expire_at'] ?: null,
            ]
        );

        $this->success(['id' => $id], '优惠码创建成功');
    }

    /**
     * 更新优惠码
     * PUT /api/coupons/{id}
     */
    public function update(int $id): void
    {
        $input = $this->getJsonInput();
        $db = Database::getInstance();

        $coupon = $db->fetch("SELECT * FROM {$db->table('coupons')} WHERE id = ?", [$id]);
        if (!$coupon) {
            $this->error('优惠码不存在', 404);
        }

        $updates = [];
        $params = [];
        foreach (['name', 'discount_percent', 'max_use_count', 'min_amount', 'project_ids', 'status', 'expire_at'] as $field) {
            if (array_key_exists($field, $input)) {
                $updates[] = "`{$field}` = ?";
                $val = $input[$field];
                if ($field === 'discount_percent') {
                    if ((float) $val <= 0 || (float) $val > 100) {
                        $this->error('折扣百分比必须在1-100之间');
                    }
                    $val = (float) $val;
                }
                $params[] = $field === 'expire_at' ? ($val ?: null) : $val;
            }
        }

        if (empty($updates)) {
            $this->error('没有需要更新的字段');
        }

        $params[] = $id;
        $db->execute("UPDATE {$db->table('coupons')} SET " . implode(', ', $updates) . ' WHERE id = ?', $params);

        $this->success(null, '更新成功');
    }

    /**
     * 删除优惠码
     * DELETE /api/coupons/{id}
     */
    public function delete(int $id): void
    {
        $db = Database::getInstance();
        $db->execute("DELETE FROM {$db->table('coupons')} WHERE id = ?", [$id]);
        $this->success(null, '删除成功');
    }

    /**
     * 导出优惠码
     * GET /api/coupons/export
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
                $where .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = array_merge($params, $ids);
            }
        }

        $rows = $db->fetchAll(
            "SELECT * FROM {$db->table('coupons')} {$where} ORDER BY id DESC LIMIT 50000",
            $params
        );

        if ($format === 'excel') {
            $filename = 'coupons_export_' . date('YmdHis') . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache');
            $html = '<table border="1"><tr><th>ID</th><th>优惠码</th><th>名称</th><th>折扣%</th><th>已用/上限</th><th>最低消费</th><th>状态</th><th>过期时间</th><th>创建时间</th></tr>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($row['id']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['code']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['name'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['discount_percent']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['used_count'] . ' / ' . ($row['max_use_count'] > 0 ? $row['max_use_count'] : '不限')) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['min_amount']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['status'] == 1 ? '启用' : '禁用') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['expire_at'] ?? '永久有效') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            echo $html;
            exit;
        }

        $filename = 'coupons_export_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['ID', '优惠码', '名称', '折扣%', '已用/上限', '最低消费', '状态', '过期时间', '创建时间']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['code'],
                $row['name'] ?? '',
                $row['discount_percent'],
                $row['used_count'] . ' / ' . ($row['max_use_count'] > 0 ? $row['max_use_count'] : '不限'),
                $row['min_amount'],
                $row['status'] == 1 ? '启用' : '禁用',
                $row['expire_at'] ?? '永久有效',
                $row['created_at'],
            ]);
        }
        fclose($output);
        exit;
    }

    /**
     * 公开接口：验证优惠码
     * POST /api/public/coupons/validate
     * Body: { code, project_id, amount }
     */
    public function validate(): void
    {
        $input = $this->getJsonInput();
        $code = strtoupper(trim($input['code'] ?? ''));
        $projectId = (int) ($input['project_id'] ?? 0);
        $amount = (float) ($input['amount'] ?? 0);

        if (empty($code)) {
            $this->error('请输入优惠码');
        }

        $db = Database::getInstance();
        $coupon = $db->fetch(
            "SELECT * FROM {$db->table('coupons')} WHERE code = ? AND status = 1",
            [$code]
        );

        if (!$coupon) {
            $this->success(['valid' => false, 'message' => '优惠码不存在或已失效']);
            return;
        }

        if ($coupon['expire_at'] && strtotime($coupon['expire_at']) < time()) {
            $this->success(['valid' => false, 'message' => '优惠码已过期']);
            return;
        }

        if ($coupon['max_use_count'] > 0 && $coupon['used_count'] >= $coupon['max_use_count']) {
            $this->success(['valid' => false, 'message' => '优惠码已达使用上限']);
            return;
        }

        if ($coupon['min_amount'] > 0 && $amount < $coupon['min_amount']) {
            $this->success(['valid' => false, 'message' => "订单金额需满¥{$coupon['min_amount']}"]);
            return;
        }

        if ($coupon['project_ids'] && $projectId) {
            $allowedProjects = array_map('trim', explode(',', $coupon['project_ids']));
            if (!in_array((string) $projectId, $allowedProjects)) {
                $this->success(['valid' => false, 'message' => '该优惠码不适用于此项目']);
                return;
            }
        }

        $discountAmount = round($amount * ($coupon['discount_percent'] / 100), 2);

        $this->success([
            'valid' => true,
            'message' => '优惠码有效',
            'coupon_id' => $coupon['id'],
            'code' => $coupon['code'],
            'discount_percent' => (float) $coupon['discount_percent'],
            'discount_amount' => $discountAmount,
            'final_amount' => max(0, round($amount - $discountAmount, 2)),
        ]);
    }
}