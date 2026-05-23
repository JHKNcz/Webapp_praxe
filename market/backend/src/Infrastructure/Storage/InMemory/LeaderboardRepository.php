<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\InMemory;

use Market\Domain\Entity\LeaderboardEntry;

final class LeaderboardRepository
{
    /** @var array<int, LeaderboardEntry> */
    private array $entries = [];

    /** @var array<string, LeaderboardEntry> */
    private array $live = [];

    public function add(LeaderboardEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    public function upsertLive(LeaderboardEntry $entry): void
    {
        $this->live[$entry->getSessionId()] = $entry;
    }

    public function removeLive(string $sessionId, string $displayName = ''): void
    {
        unset($this->live[$sessionId]);
    }

    /** @return array<int, LeaderboardEntry> */
    public function topLive(int $limit = 10): array
    {
        $entries = array_values($this->live);
        usort($entries, static fn (LeaderboardEntry $left, LeaderboardEntry $right): int => $right->getScore() <=> $left->getScore());

        return array_slice($entries, 0, $limit);
    }

    /** @return array<int, LeaderboardEntry> */
    public function top(int $limit = 10): array
    {
        usort($this->entries, static fn (LeaderboardEntry $left, LeaderboardEntry $right): int => $right->getScore() <=> $left->getScore());

        return array_slice($this->entries, 0, $limit);
    }
}
