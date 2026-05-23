<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\Redis;

use Market\Domain\Entity\PricePoint;

final class RedisPriceHistoryRepository
{
    private const MAX_POINTS = 120;

    public function __construct(private readonly RedisClient $redis)
    {
    }

    public function append(PricePoint $pricePoint): void
    {
        $key = $this->historyKey($pricePoint->getAssetId());
        $payload = json_encode($pricePoint->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return;
        }

        $this->redis->lpush($key, $payload);
        $this->redis->ltrim($key, 0, self::MAX_POINTS - 1);
    }

    public function last(string $assetId): ?PricePoint
    {
        $rows = $this->redis->lrange($this->historyKey($assetId), 0, 0);

        if ($rows === []) {
            return null;
        }

        return $this->decode($rows[0]);
    }

    /** @return array<int, PricePoint> */
    public function history(string $assetId, int $limit = 20): array
    {
        $rows = $this->redis->lrange($this->historyKey($assetId), 0, max(0, $limit - 1));
        $points = [];

        foreach ($rows as $row) {
            $point = $this->decode($row);

            if ($point !== null) {
                $points[] = $point;
            }
        }

        return array_reverse($points);
    }

    private function historyKey(string $assetId): string
    {
        return 'history:' . $assetId;
    }

    private function decode(string $row): ?PricePoint
    {
        $data = json_decode($row, true);

        if (!is_array($data)) {
            return null;
        }

        return PricePoint::fromArray($data);
    }
}
