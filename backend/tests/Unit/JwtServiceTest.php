<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\JwtService;

/**
 * JWT 服务单元测试
 */
class JwtServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 设置 JWT 密钥，保证测试与生产使用相同配置入口
        putenv('JWT_SECRET=test-secret-key-for-unit-tests');
        $_ENV['JWT_SECRET'] = 'test-secret-key-for-unit-tests';
    }

    public function testGenerateAndVerifyToken(): void
    {
        $service = new JwtService();
        $user = ['id' => 1, 'username' => 'admin', 'role' => 'admin'];
        $tokens = $service->generateToken($user);

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertArrayHasKey('expires_in', $tokens);
        $this->assertSame('Bearer', $tokens['token_type']);

        $decoded = $service->verifyToken($tokens['access_token']);
        $this->assertNotNull($decoded);
        $this->assertSame(1, $decoded['id']);
        $this->assertSame('admin', $decoded['username']);
        $this->assertSame('admin', $decoded['role']);
    }

    public function testVerifyExpiredToken(): void
    {
        $service = new JwtService();
        // 构造一个已过期 token：签发时间一年前，过期时间一天前
        $payload = [
            'iss' => 'cardauth',
            'iat' => time() - 86400 * 365,
            'exp' => time() - 86400,
            'nbf' => time() - 86400 * 365,
            'sub' => 1,
            'data' => ['id' => 1, 'username' => 'admin', 'role' => 'admin'],
        ];

        $reflection = new \ReflectionClass($service);
        $encodeMethod = $reflection->getMethod('encode');
        $encodeMethod->setAccessible(true);
        $expiredToken = $encodeMethod->invoke($service, $payload);

        $this->assertNull($service->verifyToken($expiredToken));
    }

    public function testVerifyInvalidToken(): void
    {
        $service = new JwtService();
        $this->assertNull($service->verifyToken('not.a.token'));
        $this->assertNull($service->verifyToken(''));
    }

    public function testRefreshTokenFlow(): void
    {
        $service = new JwtService();
        $user = ['id' => 2, 'username' => 'operator', 'role' => 'project_admin'];
        $tokens = $service->generateToken($user);

        $refreshed = $service->refreshToken($tokens['refresh_token']);
        $this->assertNotNull($refreshed);
        $this->assertArrayHasKey('access_token', $refreshed);

        // 使用 access_token 验证身份
        $decoded = $service->verifyToken($refreshed['access_token']);
        $this->assertSame(2, $decoded['id']);
        $this->assertSame('project_admin', $decoded['role']);
    }
}
