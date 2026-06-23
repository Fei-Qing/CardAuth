<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * 通知控制器 - SMTP 邮件发送 + 过期提醒
 */
class NotificationController extends Controller
{
    /**
     * 获取 SMTP 配置
     * GET /api/system/smtp-config
     */
    public function getSmtpConfig(): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT `key`, `value` FROM {$db->table('configs')} WHERE `key` LIKE 'smtp_%'"
        );
        $config = [];
        foreach ($rows as $row) {
            $config[$row['key']] = $row['value'];
        }
        $this->success($config);
    }

    /**
     * 保存 SMTP 配置
     * POST /api/system/smtp-config
     */
    public function saveSmtpConfig(): void
    {
        $input = $this->getJsonInput();
        $db = Database::getInstance();

        $keys = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name', 'smtp_enabled', 'smtp_expire_days'];
        foreach ($keys as $key) {
            if (isset($input[$key])) {
                $value = is_array($input[$key]) ? json_encode($input[$key]) : (string) $input[$key];
                $exists = $db->fetchColumn("SELECT COUNT(*) FROM {$db->table('configs')} WHERE `key` = ?", [$key]);
                if ($exists) {
                    $db->execute("UPDATE {$db->table('configs')} SET `value` = ? WHERE `key` = ?", [$value, $key]);
                } else {
                    $db->execute("INSERT INTO {$db->table('configs')} (`key`, `value`, `description`) VALUES (?, ?, 'SMTP邮件配置')", [$key, $value]);
                }
            }
        }
        $this->success(null, 'SMTP配置保存成功');
    }

    /**
     * 发送测试邮件
     * POST /api/system/test-smtp
     * Body: { test_email }
     */
    public function testSmtp(): void
    {
        $input = $this->getJsonInput();
        $testEmail = trim($input['test_email'] ?? '');
        if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('请输入有效的测试邮箱地址');
        }

        $config = $this->getSmtpSettings();
        if (!$config['smtp_enabled']) {
            $this->error('SMTP 未启用');
        }

        $result = $this->sendMail(
            $testEmail,
            'CardAuth 测试邮件',
            '<h3>CardAuth 邮件配置测试</h3><p>如果您收到此邮件，说明 SMTP 配置正确。</p><p>发送时间：' . date('Y-m-d H:i:s') . '</p>'
        );

        if ($result === true) {
            $this->success(null, '测试邮件发送成功，请检查收件箱');
        } else {
            $this->error('发送失败：' . $result);
        }
    }

    /**
     * 检查即将过期的授权并发送邮件通知
     * POST /api/system/notify-expiring
     * 也可通过 GET 由 cron 调用
     */
    public function notifyExpiring(): void
    {
        $config = $this->getSmtpSettings();
        if (!$config['smtp_enabled']) {
            $this->error('SMTP 未启用，无法发送通知');
        }

        $db = Database::getInstance();
        $days = (int) ($config['smtp_expire_days'] ?? 7);
        if ($days < 1) $days = 7;

        // 查找即将过期的活跃授权
        $expiring = $db->fetchAll(
            "SELECT a.id, a.bot_qq, a.contact_qq, a.contact_name, a.project_name,
                    a.duration_days, a.expire_time, a.authorized_at,
                    DATEDIFF(a.expire_time, NOW()) AS days_left
             FROM {$db->table('authorizations')} a
             WHERE a.status = 'active'
               AND a.expire_time IS NOT NULL
               AND a.expire_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
               AND a.contact_qq IS NOT NULL AND a.contact_qq != ''
             ORDER BY a.expire_time ASC",
            [$days]
        );

        if (empty($expiring)) {
            $this->success(['sent' => 0, 'message' => '没有即将过期的授权']);
            return;
        }

        $sent = 0;
        $failed = 0;
        $sentUsers = []; // 去重：同一 contact_qq 只发一封汇总邮件

        foreach ($expiring as $auth) {
            $email = $auth['contact_qq'] . '@qq.com';
            if (in_array($email, $sentUsers)) continue;
            $sentUsers[] = $email;

            // 汇总该用户的即将过期授权
            $userAuths = array_filter($expiring, fn($a) => $a['contact_qq'] === $auth['contact_qq']);
            $rows = '';
            foreach ($userAuths as $a) {
                $rows .= "<tr>
                    <td style='padding:8px 12px;border:1px solid #e2e8f0'>#{$a['id']}</td>
                    <td style='padding:8px 12px;border:1px solid #e2e8f0'>{$a['project_name']}</td>
                    <td style='padding:8px 12px;border:1px solid #e2e8f0'>{$a['bot_qq']}</td>
                    <td style='padding:8px 12px;border:1px solid #e2e8f0;color:#e74c3c;font-weight:600'>还剩 {$a['days_left']} 天</td>
                    <td style='padding:8px 12px;border:1px solid #e2e8f0'>{$a['expire_time']}</td>
                </tr>";
            }

            $html = "<div style='max-width:600px;margin:0 auto;font-family:-apple-system,sans-serif'>
                <div style='background:#3b82f6;color:#fff;padding:20px;border-radius:12px 12px 0 0'>
                    <h2 style='margin:0;font-size:18px'>授权即将过期提醒</h2>
                </div>
                <div style='background:#fff;padding:20px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px'>
                    <p>您好，以下授权即将在 <b style='color:#e74c3c'>{$days}</b> 天内过期，请及时续费：</p>
                    <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                        <tr style='background:#f8f9fb'>
                            <th style='padding:8px 12px;border:1px solid #e2e8f0;text-align:left'>授权ID</th>
                            <th style='padding:8px 12px;border:1px solid #e2e8f0;text-align:left'>项目</th>
                            <th style='padding:8px 12px;border:1px solid #e2e8f0;text-align:left'>机器人QQ</th>
                            <th style='padding:8px 12px;border:1px solid #e2e8f0;text-align:left'>剩余时间</th>
                            <th style='padding:8px 12px;border:1px solid #e2e8f0;text-align:left'>过期时间</th>
                        </tr>
                        {$rows}
                    </table>
                    <p style='color:#94a3b8;font-size:12px'>此邮件由 CardAuth 授权系统自动发送，无需回复。</p>
                </div>
            </div>";

            $result = $this->sendMail($email, '【CardAuth】授权即将过期提醒', $html);
            if ($result === true) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $this->logAction('notify_expiring', 'authorization', 0, [
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($expiring),
        ]);

        $this->success([
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($expiring),
        ], "发送完成：成功 {$sent} 封，失败 {$failed} 封");
    }

    /**
     * 获取 SMTP 配置数组
     */
    private function getSmtpSettings(): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll("SELECT `key`, `value` FROM {$db->table('configs')} WHERE `key` LIKE 'smtp_%'");
        $config = [
            'smtp_host' => '',
            'smtp_port' => '465',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_encryption' => 'ssl',
            'smtp_from_email' => '',
            'smtp_from_name' => 'CardAuth',
            'smtp_enabled' => '0',
            'smtp_expire_days' => '7',
        ];
        foreach ($rows as $row) {
            $config[$row['key']] = $row['value'];
        }
        return $config;
    }

    /**
     * PHP mail 方式发送邮件（兼容大多数 SMTP 配置）
     */
    private function sendMail(string $to, string $subject, string $html): bool|string
    {
        $config = $this->getSmtpSettings();

        $host = $config['smtp_host'];
        $port = (int) $config['smtp_port'];
        $user = $config['smtp_user'];
        $pass = $config['smtp_pass'];
        $encryption = $config['smtp_encryption']; // ssl | tls
        $fromEmail = $config['smtp_from_email'] ?: $user;
        $fromName = $config['smtp_from_name'] ?: 'CardAuth';

        if (empty($host) || empty($user) || empty($pass)) {
            return 'SMTP 配置不完整';
        }

        $boundary = md5(uniqid(time()));
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "Content-Type: text/html; charset=utf-8\r\n";
        $headers .= "X-Mailer: CardAuth\r\n";

        $message = $html;

        // 使用 socket 直连 SMTP 发送（不依赖 PHPMailer）
        $errno = 0;
        $errstr = '';
        $url = ($encryption === 'ssl') ? "ssl://{$host}" : $host;
        $socket = @fsockopen($url, $port, $errno, $errstr, 30);

        if (!$socket) {
            return "连接 SMTP 服务器失败: {$errstr} ({$errno})";
        }

        $this->smtpRead($socket);
        $this->smtpCommand($socket, "EHLO CardAuth");
        $this->smtpCommand($socket, "AUTH LOGIN");
        $this->smtpCommand($socket, base64_encode($user));
        $this->smtpCommand($socket, base64_encode($pass));
        $this->smtpCommand($socket, "MAIL FROM:<{$fromEmail}>");
        $this->smtpCommand($socket, "RCPT TO:<{$to}>");
        $this->smtpCommand($socket, "DATA");
        $this->smtpCommand($socket, "{$message}\r\n.");
        $this->smtpCommand($socket, "QUIT");

        fclose($socket);
        return true;
    }

    private function smtpCommand($socket, string $command): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->smtpRead($socket);
    }

    private function smtpRead($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    }
}
