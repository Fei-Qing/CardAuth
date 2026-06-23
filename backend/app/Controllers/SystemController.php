<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;

/**
 * 系统配置控制器
 */
class SystemController extends Controller
{
    /**
     * 获取系统配置 (支持批量获取)
     * GET /api/system/configs?keys=key1,key2,key3
     */
    public function getConfigs(): void
    {
        $keysStr = $_GET['keys'] ?? '';
        $db = Database::getInstance();

        if ($keysStr) {
            $keys = explode(',', $keysStr);
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $rows = $db->fetchAll(
                "SELECT `key`, `value` FROM {$db->table('configs')} WHERE `key` IN ({$placeholders})",
                $keys
            );
        } else {
            $rows = $db->fetchAll(
                "SELECT `key`, `value`, `description` FROM {$db->table('configs')} ORDER BY id ASC"
            );
        }

        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row['value'];
        }

        $this->success($result);
    }

    /**
     * 保存系统配置 (批量)
     * POST /api/system/configs
     * Body: { "configs": { "key1": "value1", "key2": "value2" } }
     */
    public function saveConfigs(): void
    {
        $input = $this->getJsonInput();
        $configs = $input['configs'] ?? [];

        if (empty($configs) || !is_array($configs)) {
            $this->error('参数错误');
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            foreach ($configs as $key => $value) {
                // 使用 INSERT ... ON DUPLICATE KEY UPDATE
                $db->execute(
                    "INSERT INTO {$db->table('configs')} (`key`, `value`) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                    [$key, $value]
                );
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            $this->error('保存失败: ' . $e->getMessage());
            return;
        }

        logger('update_system_config', 'config', 0, $configs);
        $this->success(null, '配置保存成功');
    }

    /**
     * 测试支付网关连接
     * POST /api/system/test-payment
     */
    public function testPayment(): void
    {
        $input = $this->getJsonInput();
        $apiUrl = $input['api_url'] ?? '';
        $appId  = $input['app_id'] ?? '';
        $appKey = $input['app_key'] ?? '';

        if (empty($apiUrl) || empty($appId) || empty($appKey)) {
            $this->error('参数不完整');
        }

        // 构建测试请求参数
        $params = [
            'pid'          => $appId,
            'type'         => 'alipay',
            'out_trade_no' => 'TEST_' . date('YmdHis'),
            'notify_url'   => '',
            'return_url'   => '',
            'name'         => 'test',
            'money'        => '0.01',
        ];

        ksort($params);
        $signStr = '';
        foreach ($params as $k => $v) {
            $signStr .= $k . '=' . $v . '&';
        }
        $signStr = rtrim($signStr, '&');
        $params['sign'] = md5($signStr . $appKey);
        $params['sign_type'] = 'MD5';

        // 测试连接 (尝试请求网关)
        $testUrl = rtrim($apiUrl, '/submit.php') . '/api.php?act=query&pid=' . $appId . '&key=' . $appKey;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $testUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'CardAuth/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->error('连接失败: ' . $error);
            return;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->success([
                'http_code' => $httpCode,
                'response'  => $response,
            ], '连接测试成功，网关可达');
        } else {
            $this->error("连接失败，HTTP状态码: {$httpCode}");
        }
    }
}