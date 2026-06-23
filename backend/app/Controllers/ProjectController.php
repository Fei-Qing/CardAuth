<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;

/**
 * 项目管理控制器
 */
class ProjectController extends Controller
{
    /**
     * 项目列表
     */
    public function list(): void
    {
        $db = Database::getInstance();
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize   = min((int) ($_GET['page_size'] ?? 20), 100);
        $keyword    = trim($_GET['keyword'] ?? '');
        $offset     = ($page - 1) * $pageSize;
        $userRole = $this->getUserRole();
        $userId   = $this->getUserId();

        $where = "WHERE deleted_at IS NULL";
        $params = [];

        // 项目管理员只能看到自己的项目
        if ($userRole === 'project_admin') {
            $user = $this->getCurrentUser();
            $projectIds = json_decode($user['project_ids'] ?? '[]', true) ?: [];
            if (empty($projectIds)) {
                $this->success(['list' => [], 'total' => 0, 'page' => $page, 'page_size' => $pageSize]);
                return;
            }
            $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
            $where .= " AND id IN ({$placeholders})";
            $params = $projectIds;
        }

        if ($keyword) {
            $where .= " AND name LIKE ?";
            $params[] = "%{$keyword}%";
        }

        $total = $db->fetchColumn("SELECT COUNT(*) FROM {$db->table('projects')} {$where}", $params);
        $projects = $db->fetchAll(
            "SELECT * FROM {$db->table('projects')} {$where} ORDER BY id DESC LIMIT {$offset}, {$pageSize}",
            $params
        );

        $this->success([
            'list'      => $projects,
            'total'     => (int) $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 项目详情
     */
    public function detail(int $id): void
    {
        $db = Database::getInstance();
        $project = $db->fetch("SELECT * FROM {$db->table('projects')} WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$project) {
            $this->error('项目不存在', 404);
        }

        // 获取卡密类型
        $types = $db->fetchAll(
            "SELECT * FROM {$db->table('card_types')} WHERE project_id = ? AND status = 1 ORDER BY sort ASC",
            [$id]
        );
        $project['card_types'] = $types;

        $this->success($project);
    }

    /**
     * 创建项目
     */
    public function create(): void
    {
        $input = $this->getJsonInput();
        $validator = new Validator($input);
        if (!$validator->validate(['name' => 'required|min:1|max:100'])) {
            $this->error($validator->getFirstError());
        }

        $db = Database::getInstance();
        $apiKey = 'ak_' . bin2hex(random_bytes(16));

        $id = $db->insert(
            "INSERT INTO {$db->table('projects')} (name, description, api_key, status) VALUES (?, ?, ?, ?)",
            [$input['name'], $input['description'] ?? '', $apiKey, $input['status'] ?? 1]
        );

        logger('create_project', 'project', $id, ['name' => $input['name']]);

        $this->success(['id' => $id, 'api_key' => $apiKey], '项目创建成功');
    }

    /**
     * 更新项目
     */
    public function update(int $id): void
    {
        $input = $this->getJsonInput();
        $db = Database::getInstance();

        $project = $db->fetch("SELECT id FROM {$db->table('projects')} WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$project) {
            $this->error('项目不存在');
        }

        $updates = [];
        $params = [];
        foreach (['name', 'description', 'status'] as $field) {
            if (isset($input[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $input[$field];
            }
        }

        if (empty($updates)) {
            $this->error('没有需要更新的字段');
        }

        $params[] = $id;
        $db->execute("UPDATE {$db->table('projects')} SET " . implode(', ', $updates) . " WHERE id = ?", $params);

        logger('update_project', 'project', $id);

        $this->success(null, '更新成功');
    }

    /**
     * 删除项目
     */
    public function delete(int $id): void
    {
        $db = Database::getInstance();
        $db->execute("UPDATE {$db->table('projects')} SET deleted_at = NOW() WHERE id = ?", [$id]);

        logger('delete_project', 'project', $id);

        $this->success(null, '删除成功');
    }

    /**
     * 重新生成API Key
     */
    public function regenerateApiKey(int $id): void
    {
        $db = Database::getInstance();
        $apiKey = 'ak_' . bin2hex(random_bytes(16));
        $db->execute("UPDATE {$db->table('projects')} SET api_key = ? WHERE id = ?", [$apiKey, $id]);

        logger('regenerate_api_key', 'project', $id);

        $this->success(['api_key' => $apiKey], 'API Key已重新生成');
    }

    /**
     * 获取项目的卡密类型列表
     */
    public function cardTypes(int $projectId): void
    {
        $db = Database::getInstance();
        $types = $db->fetchAll(
            "SELECT * FROM {$db->table('card_types')} WHERE project_id = ? ORDER BY sort ASC",
            [$projectId]
        );
        $this->success($types);
    }

    /**
     * 创建卡密类型
     */
    public function createCardType(int $projectId): void
    {
        $input = $this->getJsonInput();
        $validator = new Validator($input);
        if (!$validator->validate([
            'name'          => 'required|min:1|max:50',
            'duration_days' => 'required|integer',
            'price'         => 'required|numeric',
            'agent_cost'    => 'required|numeric',
        ])) {
            $this->error($validator->getFirstError());
        }

        $db = Database::getInstance();
        $id = $db->insert(
            "INSERT INTO {$db->table('card_types')} (project_id, name, description, duration_days, price, original_price, agent_cost, sort, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $projectId,
                $input['name'],
                $input['description'] ?? '',
                (int) $input['duration_days'],
                (float) $input['price'],
                (float) ($input['original_price'] ?? 0),
                (float) $input['agent_cost'],
                $input['sort'] ?? 0,
                $input['status'] ?? 1,
            ]
        );

        logger('create_card_type', 'card_type', $id, ['project_id' => $projectId, 'name' => $input['name']]);

        $this->success(['id' => $id], '卡密类型创建成功');
    }

    /**
     * 更新卡密类型
     */
    public function updateCardType(int $projectId, int $typeId): void
    {
        $input = $this->getJsonInput();
        $db = Database::getInstance();

        $updates = [];
        $params = [];
        foreach (['name', 'description', 'duration_days', 'price', 'original_price', 'agent_cost', 'sort', 'status'] as $field) {
            if (isset($input[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $input[$field];
            }
        }

        if (empty($updates)) {
            $this->error('没有需要更新的字段');
        }

        $params[] = $typeId;
        $params[] = $projectId;
        $db->execute(
            "UPDATE {$db->table('card_types')} SET " . implode(', ', $updates) . " WHERE id = ? AND project_id = ?",
            $params
        );

        $this->success(null, '更新成功');
    }

    /**
     * 删除卡密类型
     */
    public function deleteCardType(int $projectId, int $typeId): void
    {
        $db = Database::getInstance();
        $db->execute("DELETE FROM {$db->table('card_types')} WHERE id = ? AND project_id = ?", [$typeId, $projectId]);
        $this->success(null, '删除成功');
    }

    /**
     * 所有项目简单列表 (下拉选项用)
     */
    public function allProjects(): void
    {
        $db = Database::getInstance();
        $projects = $db->fetchAll(
            "SELECT id, name FROM {$db->table('projects')} WHERE status = 1 AND deleted_at IS NULL ORDER BY id DESC"
        );
        $this->success($projects);
    }

    /**
     * 获取所有项目的商品列表 (全量，用于商品管理页面)
     */
    public function allProducts(): void
    {
        $db = Database::getInstance();
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize   = min((int) ($_GET['page_size'] ?? 20), 100);
        $offset     = ($page - 1) * $pageSize;
        $projectId  = $_GET['project_id'] ?? '';
        $keyword    = trim($_GET['keyword'] ?? '');
        $status     = $_GET['status'] ?? '';
        $sortBy     = in_array($_GET['sort_by'] ?? '', ['id', 'name', 'price', 'agent_cost', 'sort', 'status', 'created_at']) ? $_GET['sort_by'] : 'id';
        $sortOrder  = in_array(strtolower($_GET['sort_order'] ?? ''), ['asc', 'desc']) ? strtoupper($_GET['sort_order']) : 'DESC';

        $where = 'WHERE 1=1';
        $params = [];

        if ($projectId) {
            $where .= ' AND ct.project_id = ?';
            $params[] = $projectId;
        }
        if ($status !== '') {
            $where .= ' AND ct.status = ?';
            $params[] = (int) $status;
        }
        if ($keyword) {
            $where .= ' AND ct.name LIKE ?';
            $params[] = "%{$keyword}%";
        }

        $total = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('card_types')} ct {$where}",
            $params
        );

        $products = $db->fetchAll(
            "SELECT ct.*, p.name AS project_name 
             FROM {$db->table('card_types')} ct 
             LEFT JOIN {$db->table('projects')} p ON ct.project_id = p.id 
             {$where} 
             ORDER BY ct.{$sortBy} {$sortOrder} 
             LIMIT {$offset}, {$pageSize}",
            $params
        );

        $this->success([
            'list'      => $products,
            'total'     => (int) $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 导出商品
     * GET /api/products/export
     * Query: ids, format(csv|excel)
     */
    public function productsExport(): void
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
                $where .= ' AND ct.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = array_merge($params, $ids);
            }
        }

        $rows = $db->fetchAll(
            "SELECT ct.*, p.name AS project_name 
             FROM {$db->table('card_types')} ct 
             LEFT JOIN {$db->table('projects')} p ON ct.project_id = p.id 
             {$where} 
             ORDER BY ct.id DESC LIMIT 50000",
            $params
        );

        if ($format === 'excel') {
            $filename = 'products_export_' . date('YmdHis') . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache');
            $html = '<table border="1"><tr><th>ID</th><th>所属项目</th><th>商品名称</th><th>有效天数</th><th>售价</th><th>原价</th><th>代理价格</th><th>排序</th><th>状态</th><th>创建时间</th></tr>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($row['id']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['project_name'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['duration_days'] == 0 ? '永久' : $row['duration_days'] . '天') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['price']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['original_price'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['agent_cost']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['sort']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['status'] == 1 ? '启用' : '禁用') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            echo $html;
            exit;
        }

        $filename = 'products_export_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['ID', '所属项目', '商品名称', '有效天数', '售价', '原价', '代理价格', '排序', '状态', '创建时间']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['project_name'] ?? '',
                $row['name'],
                $row['duration_days'] == 0 ? '永久' : $row['duration_days'] . '天',
                $row['price'],
                $row['original_price'] ?? '',
                $row['agent_cost'],
                $row['sort'],
                $row['status'] == 1 ? '启用' : '禁用',
                $row['created_at'],
            ]);
        }
        fclose($output);
        exit;
    }

    /**
     * 导出项目
     * GET /api/projects/export
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
        $where = 'WHERE deleted_at IS NULL';
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
            "SELECT id, name, description, api_key, status, created_at FROM {$db->table('projects')} {$where} ORDER BY id DESC LIMIT 50000",
            $params
        );

        if ($format === 'excel') {
            $filename = 'projects_export_' . date('YmdHis') . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache');
            $html = '<table border="1"><tr><th>ID</th><th>项目名称</th><th>描述</th><th>API Key</th><th>状态</th><th>创建时间</th></tr>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($row['id']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['description'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['api_key']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['status'] == 1 ? '启用' : '禁用') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            echo $html;
            exit;
        }

        $filename = 'projects_export_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['ID', '项目名称', '描述', 'API Key', '状态', '创建时间']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['name'],
                $row['description'] ?? '',
                $row['api_key'],
                $row['status'] == 1 ? '启用' : '禁用',
                $row['created_at'],
            ]);
        }
        fclose($output);
        exit;
    }
}