<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\Redis;

use Market\Domain\Entity\LeaderboardEntry;

final class RedisLeaderboardRepository
{
    private const KEY = 'leaderboard:global';

    public function __construct(private readonly RedisClient $redis)
    {
    }

    public function add(LeaderboardEntry $entry): void
    {
        $member = sprintf(
            '%s:%s:%d',
            $entry->getDisplayName(),
            $entry->getSessionId(),
            $entry->getCreatedAt()
        );

        $this->redis->zadd(self::KEY, $entry->getScore(), $member);
    }

    /** @return array<int, LeaderboardEntry> */
    public function top(int $limit = 10): array
    {
        $rows = $this->redis->zrevrangeWithScores(self::KEY, 0, max(0, $limit - 1));
        $entries = [];

        foreach ($rows as $row) {
            $parts = explode(':', $row['member'], 3);
            $displayName = $parts[0] ?? 'Guest';
            $sessionId = $parts[1] ?? '';
            $createdAt = isset($parts[2]) ? (int) $parts[2] : time();

            $entries[] = new LeaderboardEntry(
                $sessionId,
                $displayName,
                $row['score'],
                $createdAt
            );
        }

        return $entries;
    }
}
