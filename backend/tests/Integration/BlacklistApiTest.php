<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\JwtService;

/**
 * 黑名单接口集成测试
 */
class BlacklistApiTest extends TestCase
{
    private static string $baseUrl;
    private static $serverProcess;
    private static string $token;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = 'http://127.0.0.1:8092';
        $cmd = 'php -S 127.0.0.1:8092 -t ' . BASE_PATH . '/public';
        self::$serverProcess = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        sleep(1);

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

    public function testCreateAndListBlacklist(): void
    {
        $uniqueIp = '10.0.0.' . mt_rand(2, 254);
        $createResp = self::request('POST', '/api/blacklists', [
            'target_type' => 'ip',
            'target_value' => $uniqueIp,
            'remark' => 'integration test',
        ], [
            'Authorization: Bearer ' . self::$token,
            'Content-Type: application/json',
        ]);
        $this->assertSame(200, $createResp['code']);

        $listResp = self::request('GET', '/api/blacklists?target_type=ip&keyword=' . $uniqueIp, [], [
            'Authorization: Bearer ' . self::$token,
        ]);
        $this->assertSame(200, $listResp['code']);
        $this->assertGreaterThan(0, $listResp['data']['total']);
        $this->assertSame($uniqueIp, $listResp['data']['list'][0]['target_value']);
    }

    public function testUpdateBlacklistStatus(): void
    {
        $createResp = self::request('POST', '/api/blacklists', [
            'target_type' => 'user',
            'target_value' => (string)mt_rand(100000, 999999),
        ], [
            'Authorization: Bearer ' . self::$token,
            'Content-Type: application/json',
        ]);
        $this->assertSame(200, $createResp['code']);

        $listResp = self::request('GET', '/api/blacklists?target_type=user', [], [
            'Authorization: Bearer ' . self::$token,
        ]);
        $id = $listResp['data']['list'][0]['id'];

        $updateResp = self::request('PUT', '/api/blacklists/' . $id, [
            'status' => 0,
        ], [
            'Authorization: Bearer ' . self::$token,
            'Content-Type: application/json',
        ]);
        $this->assertSame(200, $updateResp['code']);
    }

    public function testNonAdminCannotAccessBlacklist(): void
    {
        $jwtService = new JwtService();
        $token = $jwtService->generateToken([
            'id' => 2,
            'username' => 'agent',
            'role' => 'agent',
        ])['access_token'];

        $resp = self::request('GET', '/api/blacklists', [], [
            'Authorization: Bearer ' . $token,
        ]);
        $this->assertSame(403, $resp['code']);
    }

    public function testBlockedUserCannotAccessApi(): void
    {
        // 将用户 9999 加入黑名单
        self::request('POST', '/api/blacklists', [
            'target_type' => 'user',
            'target_value' => '9999',
        ], [
            'Authorization: Bearer ' . self::$token,
            'Content-Type: application/json',
        ]);

        $jwtService = new JwtService();
        $token = $jwtService->generateToken([
            'id' => 9999,
            'username' => 'blocked_user',
            'role' => 'admin',
        ])['access_token'];

        $resp = self::request('GET', '/api/auth/me', [], [
            'Authorization: Bearer ' . $token,
        ]);
        $this->assertSame(403, $resp['code']);
        $this->assertStringContainsString('黑名单', $resp['message']);
    }
}
