<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\Redis;

use Market\Domain\Entity\LeaderboardEntry;

final class RedisLeaderboardRepository
{
    private const KEY_GLOBAL = 'leaderboard:global';
    private const KEY_LIVE = 'leaderboard:live';

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

        $this->redis->zadd(self::KEY_GLOBAL, $entry->getScore(), $member);
    }

    public function upsertLive(LeaderboardEntry $entry): void
    {
        $member = sprintf('%s:%s', $entry->getDisplayName(), $entry->getSessionId());
        $this->redis->zadd(self::KEY_LIVE, $entry->getScore(), $member);
    }

    public function removeLive(string $sessionId, string $displayName = ''): void
    {
        if ($displayName !== '') {
            $this->redis->zrem(self::KEY_LIVE, sprintf('%s:%s', $displayName, $sessionId));
            return;
        }

        foreach ($this->topLive(500) as $row) {
            if ($row->getSessionId() === $sessionId) {
                $member = sprintf('%s:%s', $row->getDisplayName(), $row->getSessionId());
                $this->redis->zrem(self::KEY_LIVE, $member);
            }
        }
    }

    /** @return array<int, LeaderboardEntry> */
    public function topLive(int $limit = 10): array
    {
        return $this->parseRows($this->redis->zrevrangeWithScores(self::KEY_LIVE, 0, max(0, $limit - 1)), false);
    }

    /** @return array<int, LeaderboardEntry> */
    public function top(int $limit = 10): array
    {
        return $this->parseRows($this->redis->zrevrangeWithScores(self::KEY_GLOBAL, 0, max(0, $limit - 1)), true);
    }

    /** @param array<int, array{member: string, score: float}> $rows */
    private function parseRows(array $rows, bool $withTimestamp): array
    {
        $entries = [];

        foreach ($rows as $row) {
            $parts = explode(':', $row['member'], $withTimestamp ? 3 : 2);
            $displayName = $parts[0] ?? 'Guest';
            $sessionId = $parts[1] ?? '';
            $createdAt = $withTimestamp && isset($parts[2]) ? (int) $parts[2] : time();

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
