<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\Redis;

use Market\Domain\Entity\TransactionEntry;

final class RedisTradeHistoryRepository
{
    private const MAX_ENTRIES = 100;

    public function __construct(private readonly RedisClient $redis)
    {
    }

    public function append(TransactionEntry $entry): void
    {
        $key = $this->sessionKey($entry->getSessionId());
        $payload = json_encode($entry->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return;
        }

        $this->redis->lpush($key, $payload);
        $this->redis->ltrim($key, 0, self::MAX_ENTRIES - 1);
    }

    /** @return array<int, TransactionEntry> */
    public function list(string $sessionId, int $limit = 50): array
    {
        $rows = $this->redis->lrange($this->sessionKey($sessionId), 0, max(0, $limit - 1));
        $entries = [];

        foreach ($rows as $row) {
            $data = json_decode($row, true);

            if (!is_array($data)) {
                continue;
            }

            $entries[] = TransactionEntry::fromArray($data);
        }

        return $entries;
    }

    private function sessionKey(string $sessionId): string
    {
        return 'trades:session:' . $sessionId;
    }
}
