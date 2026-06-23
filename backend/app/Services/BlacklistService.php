<?php
namespace App\Services;

use App\Core\Database;

/**
 * 黑名单服务
 * 提供黑名单查询、缓存与拦截判断
 */
class BlacklistService
{
    private string $cacheFile;
    private int $cacheTtl = 60; // 缓存60秒

    public function __construct()
    {
        $this->cacheFile = STORAGE_PATH . '/cache/blacklist.json';
    }

    /**
     * 判断指定用户ID或IP是否被拉黑
     */
    public function isBlocked(?int $userId, ?string $ip): bool
    {
        if (empty($userId) && empty($ip)) {
            return false;
        }

        $list = $this->getActiveList();
        $now = date('Y-m-d H:i:s');

        foreach ($list as $item) {
            if ($item['status'] != 1) continue;
            if (!empty($item['expire_time']) && $item['expire_time'] < $now) continue;

            if ($item['target_type'] === 'user' && (string)$userId === (string)$item['target_value']) {
                return true;
            }
            if ($item['target_type'] === 'ip' && $ip === $item['target_value']) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取生效中的黑名单列表（带缓存）
     */
    public function getActiveList(): array
    {
        if ($this->cacheValid()) {
            $data = json_decode(file_get_contents($this->cacheFile), true);
            if (is_array($data)) {
                return $data;
            }
        }

        return $this->refreshCache();
    }

    /**
     * 刷新黑名单缓存
     */
    public function refreshCache(): array
    {
        $db = Database::getInstance();
        $list = $db->fetchAll(
            "SELECT target_type, target_value, status, expire_time 
             FROM {$db->table('blacklists')}"
        );

        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->cacheFile,
            json_encode($list, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
        touch($this->cacheFile);

        return $list;
    }

    /**
     * 清除缓存
     */
    public function clearCache(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    /**
     * 检查缓存是否有效
     */
    private function cacheValid(): bool
    {
        if (!file_exists($this->cacheFile)) {
            return false;
        }
        return (time() - filemtime($this->cacheFile)) < $this->cacheTtl;
    }
}
