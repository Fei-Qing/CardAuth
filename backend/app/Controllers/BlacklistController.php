<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Services\BlacklistService;

/**
 * 黑名单管理控制器
 */
class BlacklistController extends Controller
{
    /**
     * 黑名单列表
     * GET /api/blacklists
     */
    public function list(): void
    {
        $db = Database::getInstance();
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize   = min((int) ($_GET['page_size'] ?? 20), 100);
        $offset     = ($page - 1) * $pageSize;
        $targetType = $_GET['target_type'] ?? '';
        $keyword    = trim($_GET['keyword'] ?? '');
        $status     = $_GET['status'] ?? '';
        $sortBy     = in_array($_GET['sort_by'] ?? '', ['id', 'created_at', 'updated_at']) ? $_GET['sort_by'] : 'id';
        $sortOrder  = in_array(strtolower($_GET['sort_order'] ?? ''), ['asc', 'desc']) ? strtoupper($_GET['sort_order']) : 'DESC';

        $where = "WHERE 1=1";
        $params = [];

        if ($targetType && in_array($targetType, ['user', 'ip'])) {
            $where .= " AND target_type = ?";
            $params[] = $targetType;
        }
        if ($status !== '') {
            $where .= " AND status = ?";
            $params[] = (int)$status;
        }
        if ($keyword) {
            $where .= " AND (target_value LIKE ? OR remark LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }

        $total = $db->fetchColumn("SELECT COUNT(*) FROM {$db->table('blacklists')} {$where}", $params);
        $rows = $db->fetchAll(
            "SELECT id, target_type, target_value, status, expire_time, operator_id, operator_name, remark, created_at, updated_at
             FROM {$db->table('blacklists')} {$where}
             ORDER BY {$sortBy} {$sortOrder}
             LIMIT {$offset}, {$pageSize}",
            $params
        );

        $this->success([
            'list'      => $rows,
            'total'     => (int)$total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 添加黑名单
     * POST /api/blacklists
     */
    public function create(): void
    {
        $input = $this->getJsonInput();
        $validator = new Validator($input);
        if (!$validator->validate([
            'target_type'  => 'required|in:user,ip',
            'target_value' => 'required|max:255',
        ])) {
            $this->error($validator->getFirstError());
        }

        $this->insertItem($input['target_type'], $input['target_value'], $input['expire_time'] ?? null, $input['remark'] ?? '');

        (new BlacklistService())->clearCache();
        logger('blacklist_create', 'blacklist', 0, [
            'target_type' => $input['target_type'],
            'target_value' => $input['target_value'],
            'role' => ($this->getCurrentUser()['role'] ?? ''),
        ]);

        $this->success(null, '黑名单添加成功');
    }

    /**
     * 批量添加黑名单
     * POST /api/blacklists/batch
     */
    public function batchCreate(): void
    {
        $input = $this->getJsonInput();
        $items = $input['items'] ?? [];

        if (!is_array($items) || empty($items)) {
            $this->error('请选择要添加的黑名单对象');
        }

        if (count($items) > 500) {
            $this->error('单次批量添加不能超过500条');
        }

        $success = 0;
        $failed = 0;
        foreach ($items as $item) {
            $type = $item['target_type'] ?? '';
            $value = $item['target_value'] ?? '';
            if (!in_array($type, ['user', 'ip']) || empty($value)) {
                $failed++;
                continue;
            }
            try {
                $this->insertItem($type, $value, $item['expire_time'] ?? null, $item['remark'] ?? '');
                $success++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        (new BlacklistService())->clearCache();
        logger('blacklist_batch_create', 'blacklist', 0, ['success' => $success, 'failed' => $failed, 'role' => ($this->getCurrentUser()['role'] ?? '')]);

        $this->success(['success' => $success, 'failed' => $failed], "批量添加完成，成功 {$success} 条，失败 {$failed} 条");
    }

    /**
     * 更新黑名单状态/备注/过期时间
     * PUT /api/blacklists/{id}
     */
    public function update(int $id): void
    {
        $input = $this->getJsonInput();
        $db = Database::getInstance();

        $updates = [];
        $params = [];

        if (isset($input['status']) && in_array((int)$input['status'], [0, 1], true)) {
            $updates[] = 'status = ?';
            $params[] = (int)$input['status'];
        }
        if (array_key_exists('remark', $input)) {
            $updates[] = 'remark = ?';
            $params[] = $input['remark'];
        }
        if (array_key_exists('expire_time', $input)) {
            $updates[] = 'expire_time = ?';
            $params[] = empty($input['expire_time']) ? null : $input['expire_time'];
        }

        if (empty($updates)) {
            $this->error('没有要更新的字段');
        }

        $params[] = $id;
        $db->execute(
            "UPDATE {$db->table('blacklists')} SET " . implode(', ', $updates) . " WHERE id = ?",
            $params
        );

        (new BlacklistService())->clearCache();
        logger('blacklist_update', 'blacklist', $id, array_merge($input, ['role' => ($this->getCurrentUser()['role'] ?? '')]));

        $this->success(null, '黑名单更新成功');
    }

    /**
     * 删除黑名单
     * DELETE /api/blacklists/{id}
     */
    public function delete(int $id): void
    {
        $db = Database::getInstance();
        $db->execute("DELETE FROM {$db->table('blacklists')} WHERE id = ?", [$id]);

        (new BlacklistService())->clearCache();
        logger('blacklist_delete', 'blacklist', $id, ['role' => ($this->getCurrentUser()['role'] ?? '')]);

        $this->success(null, '黑名单删除成功');
    }

    /**
     * 批量删除黑名单
     * POST /api/blacklists/batch-delete
     */
    public function batchDelete(): void
    {
        $input = $this->getJsonInput();
        $ids = $input['ids'] ?? [];

        if (!is_array($ids) || empty($ids)) {
            $this->error('请选择要删除的黑名单');
        }

        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            $this->error('无效的黑名单ID');
        }

        if (count($ids) > 500) {
            $this->error('单次批量删除不能超过500条');
        }

        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->execute("DELETE FROM {$db->table('blacklists')} WHERE id IN ({$placeholders})", $ids);

        (new BlacklistService())->clearCache();
        logger('blacklist_batch_delete', 'blacklist', 0, ['count' => count($ids), 'role' => ($this->getCurrentUser()['role'] ?? '')]);

        $this->success(['count' => count($ids)], '批量删除成功');
    }

    /**
     * 导入黑名单（CSV）
     * POST /api/blacklists/import
     */
    public function import(): void
    {
        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->error('请上传CSV文件');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $this->error('无法读取文件');
        }

        // 跳过 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header || !in_array('target_type', $header) || !in_array('target_value', $header)) {
            fclose($handle);
            $this->error('CSV格式错误，必须包含 target_type 和 target_value 列');
        }

        $success = 0;
        $failed = 0;
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            if ($rowCount > 1000) break;

            $data = array_combine($header, $row);
            $type = $data['target_type'] ?? '';
            $value = $data['target_value'] ?? '';
            if (!in_array($type, ['user', 'ip']) || empty($value)) {
                $failed++;
                continue;
            }
            try {
                $this->insertItem($type, $value, $data['expire_time'] ?? null, $data['remark'] ?? '');
                $success++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }
        fclose($handle);

        (new BlacklistService())->clearCache();
        logger('blacklist_import', 'blacklist', 0, ['success' => $success, 'failed' => $failed, 'role' => ($this->getCurrentUser()['role'] ?? '')]);

        $this->success(['success' => $success, 'failed' => $failed], "导入完成，成功 {$success} 条，失败 {$failed} 条");
    }

    /**
     * 导出黑名单
     * GET /api/blacklists/export
     */
    public function export(): void
    {
        $ids = $_GET['ids'] ?? [];
        if (!empty($ids) && !is_array($ids)) {
            $ids = explode(',', $ids);
        }

        $db = Database::getInstance();
        $params = [];
        $where = "WHERE 1=1";

        if (!empty($ids)) {
            $ids = array_filter(array_map('intval', $ids));
            $where .= " AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")";
            $params = $ids;
        }

        $rows = $db->fetchAll(
            "SELECT target_type, target_value, status, expire_time, operator_name, remark, created_at
             FROM {$db->table('blacklists')} {$where} ORDER BY id DESC LIMIT 50000",
            $params
        );

        $filename = 'blacklist_export_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['target_type', 'target_value', 'status', 'expire_time', 'operator_name', 'remark', 'created_at']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['target_type'],
                $row['target_value'],
                $row['status'],
                $row['expire_time'] ?? '',
                $row['operator_name'],
                $row['remark'],
                $row['created_at'],
            ]);
        }
        fclose($output);
        exit;
    }

    /**
     * 插入单条黑名单记录
     */
    private function insertItem(string $type, string $value, ?string $expireTime, string $remark): void
    {
        $user = $this->getCurrentUser();
        $db = Database::getInstance();

        $expire = empty($expireTime) ? null : $expireTime;

        $db->execute(
            "INSERT INTO {$db->table('blacklists')} (target_type, target_value, status, expire_time, operator_id, operator_name, remark) 
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), expire_time = VALUES(expire_time), remark = VALUES(remark), updated_at = NOW()",
            [
                $type,
                $value,
                1,
                $expire,
                $user['id'] ?? 0,
                $user['username'] ?? 'system',
                $remark,
            ]
        );
    }
}
