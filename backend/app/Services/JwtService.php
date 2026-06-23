<?php
namespace App\Services;

/**
 * JWT认证服务 (原生PHP实现，无需第三方依赖)
 */
class JwtService
{
    private string $secret;
    private int $ttl;
    private int $refreshTtl;
    private string $algorithm = 'HS256';

    public function __construct()
    {
        $this->secret     = config('app.jwt_secret', '');
        $this->ttl        = config('app.jwt_ttl', 7200);
        $this->refreshTtl = config('app.jwt_refresh_ttl', 1209600);
    }

    /**
     * 生成Token
     */
    public function generateToken(array $user): array
    {
        $now = time();
        $payload = [
            'iss'      => 'cardauth',
            'iat'      => $now,
            'exp'      => $now + $this->ttl,
            'nbf'      => $now,
            'sub'      => $user['id'],
            'data'     => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'role'     => $user['role'],
            ],
        ];

        $accessToken = $this->encode($payload);

        // 刷新Token
        $refreshPayload = array_merge($payload, [
            'exp' => $now + $this->refreshTtl,
            'type' => 'refresh',
        ]);
        $refreshToken = $this->encode($refreshPayload);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => $this->ttl,
            'token_type'    => 'Bearer',
        ];
    }

    /**
     * 验证Token
     */
    public function verifyToken(string $token): ?array
    {
        $decoded = $this->decode($token);
        if ($decoded === null) {
            return null;
        }
        return $decoded['data'] ?? null;
    }

    /**
     * 刷新Token
     */
    public function refreshToken(string $refreshToken): ?array
    {
        $decoded = $this->decode($refreshToken);
        if ($decoded === null) {
            return null;
        }
        if (($decoded['type'] ?? '') !== 'refresh') {
            return null;
        }
        $user = $decoded['data'] ?? [];
        return $this->generateToken($user);
    }

    /**
     * JWT编码
     */
    private function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );
        return "$header.$payload.$signature";
    }

    /**
     * JWT解码
     */
    private function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        // 验证签名
        $expectedSig = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        $data = json_decode($this->base64UrlDecode($payload), true);
        if ($data === null) {
            return null;
        }

        // 检查过期
        if (isset($data['exp']) && $data['exp'] < time()) {
            return null;
        }

        return $data;
    }

    /**
     * Base64Url编码
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64Url解码
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}