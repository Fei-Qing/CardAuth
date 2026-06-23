<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Application;
use App\Core\Database;
use App\Core\Validator;
use App\Services\JwtService;

/**
 * 认证控制器
 * 处理登录、登出、Token刷新、密码修改
 */
class AuthController extends Controller
{
    /**
     * 管理员登录
     * POST /api/auth/admin-login
     */
    public function adminLogin(): void
    {
        $this->doLogin(['admin', 'project_admin'], 'admin');
    }

    /**
     * 代理登录
     * POST /api/auth/agent-login
     */
    public function agentLogin(): void
    {
        $this->doLogin(['agent'], 'agent');
    }

    /**
     * 统一登录处理
     * @param array $allowedRoles 允许登录的角色
     * @param string $portal 登录入口标识，用于日志
     */
    private function doLogin(array $allowedRoles, string $portal): void
    {
        $input = $this->getJsonInput();
        $account = $input['username'] ?? ($input['email'] ?? '');

        $validator = new Validator($input);
        if (!$validator->validate([
            'password' => 'required|min:6|max:50',
        ])) {
            $this->error($validator->getFirstError());
        }

        if (empty($account)) {
            $this->error('请输入用户名或邮箱');
        }

        $db = Database::getInstance();

        // 根据是否包含@选择查询方式
        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            $user = $db->fetch(
                "SELECT * FROM {$db->table('users')} WHERE email = ? AND deleted_at IS NULL",
                [$account]
            );
        } else {
            $user = $db->fetch(
                "SELECT * FROM {$db->table('users')} WHERE username = ? AND deleted_at IS NULL",
                [$account]
            );
        }

        if (!$user || !passwordVerify($input['password'], $user['password'])) {
            $this->error('用户名或密码错误');
        }

        if ($user['status'] != 1) {
            $this->error('账号已被禁用，请联系管理员');
        }

        if (!in_array($user['role'], $allowedRoles, true)) {
            $this->error('该账号无此入口登录权限');
        }

        // 更新登录信息
        $db->execute(
            "UPDATE {$db->table('users')} SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
            [clientIp(), $user['id']]
        );

        // 生成Token
        $jwtService = new JwtService();
        $tokens = $jwtService->generateToken($user);

        logger('login', 'user', $user['id'], ['portal' => $portal, 'role' => $user['role']]);

        $this->success([
            'user' => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'nickname' => $user['nickname'],
                'role'     => $user['role'],
                'email'    => $user['email'],
            ],
            'token' => $tokens,
        ], '登录成功');
    }

    /**
     * 代理注册
     * POST /api/auth/agent-register
     */
    public function agentRegister(): void
    {
        $input = $this->getJsonInput();
        $validator = new Validator($input);
        if (!$validator->validate([
            'username'        => 'required|min:3|max:50',
            'password'        => 'required|min:6|max:50',
            'confirm_password'=> 'required',
            'email'           => 'required|email|max:100',
            'phone'           => 'required|max:20',
        ])) {
            $this->error($validator->getFirstError());
        }

        if ($input['password'] !== $input['confirm_password']) {
            $this->error('两次输入的密码不一致');
        }

        // 手机号格式验证
        if (!preg_match('/^1[3-9]\d{9}$/', $input['phone'])) {
            $this->error('手机号格式不正确');
        }

        $db = Database::getInstance();

        // 检查用户名是否已存在
        $exists = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('users')} WHERE username = ? AND deleted_at IS NULL",
            [$input['username']]
        );
        if ($exists > 0) {
            $this->error('用户名已被注册');
        }

        // 检查邮箱是否已存在
        $emailExists = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('users')} WHERE email = ? AND deleted_at IS NULL",
            [$input['email']]
        );
        if ($emailExists > 0) {
            $this->error('邮箱已被注册');
        }

        // 解析上级代理邀请码（用户名）
        $parentId = 0;
        $inviteCode = trim($input['invite_code'] ?? '');
        if ($inviteCode !== '') {
            $parent = $db->fetch(
                "SELECT id, role FROM {$db->table('users')} WHERE username = ? AND deleted_at IS NULL AND status = 1",
                [$inviteCode]
            );
            if (!$parent) {
                $this->error('邀请码不存在或已禁用');
            }
            if ($parent['role'] !== 'agent') {
                $this->error('邀请码无效，仅代理可邀请下级代理');
            }
            $parentId = (int) $parent['id'];
        }

        $userId = $db->insert(
            "INSERT INTO {$db->table('users')} (username, password, nickname, email, phone, role, parent_id, status, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, 'agent', ?, 1, NOW(), NOW())",
            [
                $input['username'],
                passwordHash($input['password']),
                $input['username'],
                $input['email'],
                $input['phone'],
                $parentId,
            ]
        );
        logger('agent_register', 'user', $userId, ['username' => $input['username'], 'parent_id' => $parentId]);

        $this->success(['id' => $userId], '注册成功，请登录');
    }

    /**
     * 刷新Token
     */
    public function refreshToken(): void
    {
        $input = $this->getJsonInput();
        $refreshToken = $input['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            $this->error('缺少刷新Token');
        }

        $jwtService = new JwtService();
        $tokens = $jwtService->refreshToken($refreshToken);

        if (!$tokens) {
            $this->error('刷新Token无效或已过期');
        }

        $this->success($tokens, 'Token刷新成功');
    }

    /**
     * 获取当前用户信息
     */
    public function me(): void
    {
        $userId = $this->getUserId();
        if (!$userId) {
            $this->error('用户不存在', 404);
        }

        $db = Database::getInstance();
        $user = $db->fetch(
            "SELECT id, username, nickname, email, phone, avatar, role, status, parent_id, last_login_at, last_login_ip, created_at
             FROM {$db->table('users')}
             WHERE id = ? AND deleted_at IS NULL",
            [$userId]
        );

        if (!$user) {
            $this->error('用户不存在', 404);
        }

        // 如果是代理，获取额度信息
        if ($user['role'] === 'agent') {
            $db = Database::getInstance();
            $agent = $db->fetch(
                "SELECT total_quota, used_quota, frozen_quota FROM {$db->table('agents')} WHERE user_id = ?",
                [$user['id']]
            );
            $user['agent_info'] = $agent ?: ['total_quota' => 0, 'used_quota' => 0, 'frozen_quota' => 0];
        }

        $this->success($user);
    }

    /**
     * 修改密码
     */
    public function changePassword(): void
    {
        $input = $this->getJsonInput();
        $validator = new Validator($input);
        if (!$validator->validate([
            'old_password' => 'required|min:6',
            'new_password' => 'required|min:6|max:50',
        ])) {
            $this->error($validator->getFirstError());
        }

        $user = $this->getCurrentUser();
        if (!passwordVerify($input['old_password'], $user['password'])) {
            $this->error('原密码错误');
        }

        $db = Database::getInstance();
        $db->execute(
            "UPDATE {$db->table('users')} SET password = ? WHERE id = ?",
            [passwordHash($input['new_password']), $user['id']]
        );

        logger('change_password', 'user', $user['id']);

        $this->success(null, '密码修改成功');
    }

    /**
     * 更新当前管理员个人资料
     * PUT /api/auth/profile
     * Body: { nickname: string, email: string, phone: string, avatar: string }
     */
    public function updateProfile(): void
    {
        $input = $this->getJsonInput();
        $validator = new Validator($input);
        if (!$validator->validate([
            'nickname' => 'max:50',
            'email'    => 'email|max:100',
            'phone'    => 'max:20',
            'avatar'   => 'max:500',
        ])) {
            $this->error($validator->getFirstError());
        }

        $userId = $this->getUserId();
        if (!$userId) {
            $this->error('用户不存在', 404);
        }

        $allowed = ['nickname', 'email', 'phone', 'avatar'];
        $updates = [];
        $params  = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $input)) {
                $updates[] = "{$field} = ?";
                $params[]  = $input[$field] ?? '';
            }
        }

        if (empty($updates)) {
            $this->error('没有要更新的字段');
        }

        $params[] = $userId;
        $db = Database::getInstance();
        $db->execute(
            "UPDATE {$db->table('users')} SET " . implode(', ', $updates) . " WHERE id = ?",
            $params
        );

        logger('update_profile', 'user', $userId, ['fields' => array_keys(array_intersect_key($input, array_flip($allowed)))]);

        $this->success(null, '个人资料更新成功');
    }

    /**
     * 上传头像
     * POST /api/auth/avatar
     * Body: multipart/form-data, field: avatar (image file)
     */
    public function uploadAvatar(): void
    {
        $userId = $this->getUserId();
        if (!$userId) {
            $this->error('用户不存在', 404);
        }

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $this->error('请选择要上传的头像图片');
        }

        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            $this->error('仅支持 JPG、PNG、GIF、WebP 格式的图片');
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            $this->error('图片大小不能超过 2MB');
        }

        // 确保上传目录存在
        $uploadDir = BASE_PATH . '/public/uploads/avatars';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // 生成唯一文件名
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $filepath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->error('头像上传失败，请重试');
        }

        // 更新数据库
        $avatarUrl = '/uploads/avatars/' . $filename;
        $db = Database::getInstance();
        $db->execute(
            "UPDATE {$db->table('users')} SET avatar = ? WHERE id = ?",
            [$avatarUrl, $userId]
        );

        // 删除旧头像文件（如果存在）
        $oldUser = $db->fetch("SELECT avatar FROM {$db->table('users')} WHERE id = ?", [$userId]);
        // 实际上新头像路径已更新，但旧文件还在，这里简单处理：不删除旧文件，避免并发问题

        logger('upload_avatar', 'user', $userId);

        $this->success(['avatar' => $avatarUrl], '头像上传成功');
    }

    /**
     * 用户列表 (Admin专用)
     * GET /api/users
     * Query: role, keyword, status, start_date, end_date, sort_by, sort_order, page, page_size
     */
    public function list(): void
    {
        $db = Database::getInstance();
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize   = min((int) ($_GET['page_size'] ?? 20), 100);
        $offset     = ($page - 1) * $pageSize;
        $role       = $_GET['role'] ?? '';
        $keyword    = trim($_GET['keyword'] ?? '');
        $status     = $_GET['status'] ?? '';
        $startDate  = $_GET['start_date'] ?? '';
        $endDate    = $_GET['end_date'] ?? '';
        $sortBy     = in_array($_GET['sort_by'] ?? '', ['id', 'username', 'nickname', 'role', 'status', 'last_login_at', 'created_at']) ? $_GET['sort_by'] : 'id';
        $sortOrder  = in_array(strtolower($_GET['sort_order'] ?? ''), ['asc', 'desc']) ? strtoupper($_GET['sort_order']) : 'DESC';

        $where = "WHERE deleted_at IS NULL";
        $params = [];

        if ($role && in_array($role, ['admin', 'project_admin', 'agent'])) {
            $where .= " AND role = ?";
            $params[] = $role;
        }
        if ($status !== '') {
            $where .= " AND status = ?";
            $params[] = (int) $status;
        }
        if ($keyword) {
            $where .= " AND (username LIKE ? OR nickname LIKE ? OR email LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        if ($startDate) {
            $where .= " AND DATE(created_at) >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $where .= " AND DATE(created_at) <= ?";
            $params[] = $endDate;
        }

        $total = $db->fetchColumn("SELECT COUNT(*) FROM {$db->table('users')} {$where}", $params);
        $users = $db->fetchAll(
            "SELECT id, username, nickname, email, phone, avatar, role, project_ids, status, last_login_at, last_login_ip, created_at
             FROM {$db->table('users')} {$where} ORDER BY {$sortBy} {$sortOrder} LIMIT {$offset}, {$pageSize}",
            $params
        );

        $this->success([
            'list'      => $users,
            'total'     => (int) $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 创建用户 (Admin)
     */
    public function create(): void
    {
        $input = $this->getJsonInput();
        $validator = new Validator($input);
        if (!$validator->validate([
            'username' => 'required|min:3|max:50',
            'password' => 'required|min:6|max:50',
            'role'     => 'required',
        ])) {
            $this->error($validator->getFirstError());
        }

        if (!in_array($input['role'], ['admin', 'project_admin', 'agent'])) {
            $this->error('无效的角色类型');
        }

        $db = Database::getInstance();

        // 检查用户名唯一
        $exist = $db->fetch("SELECT id FROM {$db->table('users')} WHERE username = ? AND deleted_at IS NULL", [$input['username']]);
        if ($exist) {
            $this->error('用户名已存在');
        }

        $userId = $db->insert(
            "INSERT INTO {$db->table('users')} (username, password, nickname, email, role, project_ids, status) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $input['username'],
                passwordHash($input['password']),
                $input['nickname'] ?? '',
                $input['email'] ?? '',
                $input['role'],
                isset($input['project_ids']) ? json_encode($input['project_ids']) : null,
                $input['status'] ?? 1,
            ]
        );

        // 如果是代理，创建额度记录
        if ($input['role'] === 'agent') {
            $db->insert(
                "INSERT INTO {$db->table('agents')} (user_id, total_quota, used_quota) VALUES (?, 0, 0)",
                [$userId]
            );
        }

        logger('create_user', 'user', $userId, ['username' => $input['username'], 'role' => $input['role']]);

        $this->success(['id' => $userId], '用户创建成功');
    }

    /**
     * 更新用户
     */
    public function update(int $id): void
    {
        $input = $this->getJsonInput();
        $db = Database::getInstance();

        $user = $db->fetch("SELECT * FROM {$db->table('users')} WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$user) {
            $this->error('用户不存在');
        }

        $updates = [];
        $params = [];

        if (isset($input['nickname'])) {
            $updates[] = 'nickname = ?';
            $params[] = $input['nickname'];
        }
        if (isset($input['email'])) {
            $updates[] = 'email = ?';
            $params[] = $input['email'];
        }
        if (isset($input['status'])) {
            $updates[] = 'status = ?';
            $params[] = $input['status'];
        }
        if (isset($input['password']) && !empty($input['password'])) {
            $updates[] = 'password = ?';
            $params[] = passwordHash($input['password']);
        }
        if (isset($input['project_ids']) && $user['role'] === 'project_admin') {
            $updates[] = 'project_ids = ?';
            $params[] = json_encode($input['project_ids']);
        }

        if (empty($updates)) {
            $this->error('没有需要更新的字段');
        }

        $params[] = $id;
        $db->execute("UPDATE {$db->table('users')} SET " . implode(', ', $updates) . " WHERE id = ?", $params);

        logger('update_user', 'user', $id);

        $this->success(null, '更新成功');
    }

    /**
     * 删除用户
     */
    public function delete(int $id): void
    {
        $db = Database::getInstance();
        $user = $db->fetch("SELECT id, role FROM {$db->table('users')} WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$user) {
            $this->error('用户不存在');
        }

        if ($user['role'] === 'admin') {
            $this->error('不能删除超级管理员');
        }

        $db->execute("UPDATE {$db->table('users')} SET deleted_at = NOW() WHERE id = ?", [$id]);

        logger('delete_user', 'user', $id);

        $this->success(null, '删除成功');
    }

    /**
     * 批量删除用户
     * POST /api/users/batch-delete
     * Body: { ids: [int] }
     */
    public function batchDelete(): void
    {
        $input = $this->getJsonInput();
        $ids = $input['ids'] ?? [];

        if (!is_array($ids) || empty($ids)) {
            $this->error('请选择要删除的用户');
        }

        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            $this->error('无效的用户ID');
        }

        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 不能删除管理员
        $adminCount = $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('users')} WHERE id IN ({$placeholders}) AND role = 'admin' AND deleted_at IS NULL",
            $ids
        );
        if ($adminCount > 0) {
            $this->error('选中的用户中包含管理员，不能删除');
        }

        $db->execute(
            "UPDATE {$db->table('users')} SET deleted_at = NOW() WHERE id IN ({$placeholders})",
            $ids
        );

        logger('batch_delete_users', 'user', 0, ['count' => count($ids)]);

        $this->success(['count' => count($ids)], '批量删除成功');
    }

    /**
     * 用户统计
     * GET /api/users/stats
     */
    public function stats(): void
    {
        $db = Database::getInstance();
        $table = $db->table('users');

        $total       = $db->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL");
        $admin       = $db->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE role = 'admin' AND deleted_at IS NULL");
        $projectAdmin = $db->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE role = 'project_admin' AND deleted_at IS NULL");
        $agent       = $db->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE role = 'agent' AND deleted_at IS NULL");

        $this->success([
            'total'        => (int) $total,
            'admin'        => (int) $admin,
            'projectAdmin' => (int) $projectAdmin,
            'agent'        => (int) $agent,
        ]);
    }

    /**
     * 导出用户
     * GET /api/users/export
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
        $table = $db->table('users');
        $where = 'WHERE deleted_at IS NULL';
        $params = [];

        if (!empty($ids)) {
            $ids = is_array($ids) ? $ids : explode(',', $ids);
            $ids = array_filter(array_map('intval', $ids));
            if (!empty($ids)) {
                $where .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = $ids;
            }
        }

        $rows = $db->fetchAll(
            "SELECT id, username, nickname, email, role, status, last_login_at, last_login_ip, created_at
             FROM {$table} {$where}
             ORDER BY id DESC LIMIT 50000",
            $params
        );

        $roleMap = ['admin' => '超级管理员', 'project_admin' => '项目管理员', 'agent' => '代理'];

        if ($format === 'excel') {
            $filename = 'users_export_' . date('YmdHis') . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache');
            $html = '<table border="1"><tr><th>ID</th><th>用户名</th><th>昵称</th><th>邮箱</th><th>角色</th><th>状态</th><th>最后登录</th><th>登录IP</th><th>创建时间</th></tr>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($row['id']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['username']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['nickname'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($roleMap[$row['role']] ?? $row['role']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['status'] == 1 ? '正常' : '禁用') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['last_login_at'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['last_login_ip'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            echo $html;
            exit;
        }

        $filename = 'users_export_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['ID', '用户名', '昵称', '邮箱', '角色', '状态', '最后登录', '登录IP', '创建时间']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['username'],
                $row['nickname'] ?? '',
                $row['email'] ?? '',
                $roleMap[$row['role']] ?? $row['role'],
                $row['status'] == 1 ? '正常' : '禁用',
                $row['last_login_at'] ?? '',
                $row['last_login_ip'] ?? '',
                $row['created_at'],
            ]);
        }
        fclose($output);
        exit;
    }
}