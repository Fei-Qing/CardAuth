<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\BlacklistService;
use App\Core\Database;

/**
 * 黑名单服务单元测试
 */
class BlacklistServiceTest extends TestCase
{
    private BlacklistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BlacklistService();
        $this->service->clearCache();
    }

    protected function tearDown(): void
    {
        $this->service->clearCache();
        parent::tearDown();
    }

    public function testIsBlockedWithUserId(): void
    {
        $this->service->refreshCache();
        // 默认数据库为空，应未被拉黑
        $this->assertFalse($this->service->isBlocked(1, null));
    }

    public function testIsBlockedWithIp(): void
    {
        $this->service->refreshCache();
        $this->assertFalse($this->service->isBlocked(null, '127.0.0.1'));
        $this->assertFalse($this->service->isBlocked(null, null));
    }

    public function testCacheRefreshAndClear(): void
    {
        $list = $this->service->refreshCache();
        $this->assertIsArray($list);

        $activeList = $this->service->getActiveList();
        $this->assertSame($list, $activeList);

        $this->service->clearCache();
        $this->assertFalse(file_exists(STORAGE_PATH . '/cache/blacklist.json'));
    }

    public function testIsBlockedWithExpiredItem(): void
    {
        // 构造一个包含过期记录的缓存文件
        $cache = [
            [
                'target_type' => 'ip',
                'target_value' => '192.168.1.100',
                'status' => 1,
                'expire_time' => date('Y-m-d H:i:s', time() - 3600),
            ],
        ];
        $file = STORAGE_PATH . '/cache/blacklist.json';
        $dir = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($file, json_encode($cache));

        $this->assertFalse($this->service->isBlocked(null, '192.168.1.100'));
    }
}
