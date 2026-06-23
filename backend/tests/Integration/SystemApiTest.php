<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\JwtService;

/**
 * 系统设置接口集成测试
 */
class SystemApiTest extends TestCase
{
    private static string $baseUrl;
    private static $serverProcess;
    private static string $token;

    public static function setUpBeforeClass(): void
    {
        // 启动独立测试服务器
        self::$baseUrl = 'http://127.0.0.1:8090';
        $cmd = 'php -S 127.0.0.1:8090 -t ' . BASE_PATH . '/public';
        self::$serverProcess = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        // 等待服务器就绪
        sleep(1);

        // 直接生成管理员 JWT，避免依赖登录密码
        $jwtService = new JwtService();
        self::$token = $jwtService->generateToken([
            'id' => 1,
            'username' => 'admin',
            'role' => 'admin',
        ])['access_token'];
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
    }

    private static function request(string $method, string $path, array $body = [], array $headers = []): array
    {
        $url = self::$baseUrl . $path;
        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if (!empty($headers)) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        if (!empty($body)) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }

    public function testGetSystemConfigs(): void
    {
        $resp = self::request('GET', '/api/system/configs?keys=site_name,card_key_prefix', [], [
            'Authorization: Bearer ' . self::$token,
        ]);

        $this->assertSame(200, $resp['code']);
        $this->assertArrayHasKey('data', $resp);
        $this->assertArrayHasKey('site_name', $resp['data']);
    }

    public function testSaveAndGetSiteName(): void
    {
        $uniqueName = 'TestSite_' . time();
        $saveResp = self::request('POST', '/api/system/configs', [
            'configs' => [
                'site_name' => $uniqueName,
            ],
        ], [
            'Authorization: Bearer ' . self::$token,
            'Content-Type: application/json',
        ]);
        $this->assertSame(200, $saveResp['code']);

        $getResp = self::request('GET', '/api/system/configs?keys=site_name', [], [
            'Authorization: Bearer ' . self::$token,
        ]);
        $this->assertSame(200, $getResp['code']);
        $this->assertSame($uniqueName, $getResp['data']['site_name']);

        // 恢复默认站点名称
        self::request('POST', '/api/system/configs', [
            'configs' => ['site_name' => 'CardAuth授权管理系统'],
        ], [
            'Authorization: Bearer ' . self::$token,
            'Content-Type: application/json',
        ]);
    }
}
