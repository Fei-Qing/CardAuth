<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\JwtService;

/**
 * 管理员个人信息接口集成测试
 */
class AuthProfileApiTest extends TestCase
{
    private static string $baseUrl;
    private static $serverProcess;
    private static string $token;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = 'http://127.0.0.1:8091';
        $cmd = 'php -S 127.0.0.1:8091 -t ' . BASE_PATH . '/public';
        self::$serverProcess = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        sleep(1);

        // 直接生成管理员 JWT
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

    public function testGetCurrentUserProfile(): void
    {
        $resp = self::request('GET', '/api/auth/me', [], [
            'Authorization: Bearer ' . self::$token,
        ]);

        $this->assertSame(200, $resp['code']);
        $this->assertSame('admin', $resp['data']['username']);
        $this->assertArrayHasKey('nickname', $resp['data']);
    }

    public function testUpdateProfile(): void
    {
        $uniqueNickname = 'Admin_' . time();
        $resp = self::request('PUT', '/api/auth/profile', [
            'nickname' => $uniqueNickname,
            'email' => 'admin_' . time() . '@example.com',
            'phone' => '1380000' . mt_rand(1000, 9999),
        ], [
            'Authorization: Bearer ' . self::$token,
            'Content-Type: application/json',
        ]);

        $this->assertSame(200, $resp['code']);

        $me = self::request('GET', '/api/auth/me', [], [
            'Authorization: Bearer ' . self::$token,
        ]);
        $this->assertSame($uniqueNickname, $me['data']['nickname']);

        // 恢复昵称
        self::request('PUT', '/api/auth/profile', [
            'nickname' => '超级管理员',
            'email' => '',
            'phone' => '',
        ], [
            'Authorization: Bearer ' . self::$token,
            'Content-Type: application/json',
        ]);
    }

    public function testUpdateProfileWithInvalidEmail(): void
    {
        $resp = self::request('PUT', '/api/auth/profile', [
            'email' => 'invalid-email',
        ], [
            'Authorization: Bearer ' . self::$token,
            'Content-Type: application/json',
        ]);

        $this->assertSame(400, $resp['code']);
    }
}
