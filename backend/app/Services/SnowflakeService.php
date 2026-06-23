<?php
namespace App\Services;

/**
 * Snowflake ID生成器
 * 用于生成唯一的卡密标识
 */
class SnowflakeService
{
    private int $epoch;
    private int $workerId;
    private int $datacenterId;
    private int $sequence = 0;
    private int $lastTimestamp = -1;

    private const WORKER_ID_BITS      = 5;
    private const DATACENTER_ID_BITS  = 5;
    private const SEQUENCE_BITS       = 12;

    public function __construct()
    {
        $this->epoch        = strtotime('2024-01-01 00:00:00') * 1000;
        $this->workerId     = (int) config('app.snowflake.worker_id', 1);
        $this->datacenterId = (int) config('app.snowflake.datacenter_id', 1);
    }

    /**
     * 生成下一个ID
     */
    public function nextId(): int
    {
        $timestamp = $this->currentTimestamp();

        if ($timestamp < $this->lastTimestamp) {
            throw new \RuntimeException('时钟回拨，拒绝生成ID');
        }

        if ($timestamp === $this->lastTimestamp) {
            $this->sequence = ($this->sequence + 1) & 4095;
            if ($this->sequence === 0) {
                $timestamp = $this->waitNextMillis($this->lastTimestamp);
            }
        } else {
            $this->sequence = 0;
        }

        $this->lastTimestamp = $timestamp;

        return (($timestamp - $this->epoch) << 22)
            | ($this->datacenterId << 17)
            | ($this->workerId << 12)
            | $this->sequence;
    }

    /**
     * 生成卡密Key (Snowflake ID + 随机字符)
     */
    public function generateCardKey(): string
    {
        $id = $this->nextId();
        $base = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $encoded = '';
        $num = $id;
        while ($num > 0) {
            $encoded = $base[$num % 36] . $encoded;
            $num = (int)($num / 36);
        }
        return strtoupper(substr(md5($id), 0, 4) . '-' . $encoded . '-' . substr(md5($id . randomStr(4)), 0, 4));
    }

    private function currentTimestamp(): int
    {
        return (int) (microtime(true) * 1000);
    }

    private function waitNextMillis(int $lastTimestamp): int
    {
        $timestamp = $this->currentTimestamp();
        while ($timestamp <= $lastTimestamp) {
            $timestamp = $this->currentTimestamp();
        }
        return $timestamp;
    }
}