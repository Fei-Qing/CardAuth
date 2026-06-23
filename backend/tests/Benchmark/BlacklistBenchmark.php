<?php
namespace Tests\Benchmark;

use App\Services\JwtService;

/**
 * 黑名单接口压力测试脚本
 * 并发请求 /api/auth/me，验证黑名单拦截性能与稳定性
 */
class BlacklistBenchmark
{
    private string $baseUrl;
    private string $token;
    private int $concurrency;
    private int $total;

    public function __construct(string $baseUrl, int $concurrency = 20, int $total = 200)
    {
        $this->baseUrl = $baseUrl;
        $this->concurrency = $concurrency;
        $this->total = $total;

        $jwtService = new JwtService();
        $this->token = $jwtService->generateToken([
            'id' => 8888,
            'username' => 'bench_user',
            'role' => 'admin',
        ])['access_token'];
    }

    public function run(): array
    {
        $completed = 0;
        $success = 0;
        $errors = 0;
        $start = microtime(true);

        $mh = curl_multi_init();
        $active = [];

        // 初始化一批请求
        for ($i = 0; $i < $this->concurrency && $i < $this->total; $i++) {
            $ch = $this->createHandle();
            $active[] = $ch;
            curl_multi_add_handle($mh, $ch);
        }

        $sent = count($active);
        $running = null;

        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 0.1);

            while ($info = curl_multi_info_read($mh)) {
                $completed++;
                $handle = $info['handle'];
                $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

                if ($httpCode >= 200 && $httpCode < 500) {
                    $success++;
                } else {
                    $errors++;
                }

                curl_multi_remove_handle($mh, $handle);

                if ($sent < $this->total) {
                    $newHandle = $this->createHandle();
                    $sent++;
                    curl_multi_add_handle($mh, $newHandle);
                }
            }
        } while ($running > 0);

        curl_multi_close($mh);
        $duration = microtime(true) - $start;

        return [
            'total' => $completed,
            'success' => $success,
            'errors' => $errors,
            'duration' => round($duration, 4),
            'rps' => $duration > 0 ? round($completed / $duration, 2) : 0,
        ];
    }

    private function createHandle()
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/auth/me',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
            ],
        ]);
        return $ch;
    }
}

// 命令行入口
if (PHP_SAPI === 'cli' && basename($argv[0]) === basename(__FILE__)) {
    define('BASE_PATH', dirname(dirname(__DIR__)));
    define('APP_PATH', BASE_PATH . '/app');
    define('CONFIG_PATH', BASE_PATH . '/config');
    define('ROUTES_PATH', BASE_PATH . '/routes');
    define('STORAGE_PATH', BASE_PATH . '/storage');

    require BASE_PATH . '/vendor/autoload.php';
    require APP_PATH . '/Helpers/functions.php';

    if (file_exists(BASE_PATH . '/.env')) {
        $lines = file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                putenv(trim($key) . '=' . trim($value, " \t\n\r\0\x0B\"'"));
            }
        }
    }

    $baseUrl = $argv[1] ?? 'http://127.0.0.1:8080';
    $concurrency = (int)($argv[2] ?? 20);
    $total = (int)($argv[3] ?? 200);

    $bench = new BlacklistBenchmark($baseUrl, $concurrency, $total);
    $result = $bench->run();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
