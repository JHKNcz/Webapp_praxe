<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\InMemory;

use Market\Domain\Entity\LeaderboardEntry;

final class LeaderboardRepository
{
    /** @var array<int, LeaderboardEntry> */
    private array $entries = [];

    public function add(LeaderboardEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    /** @return array<int, LeaderboardEntry> */
    public function top(int $limit = 10): array
    {
        usort($this->entries, static fn (LeaderboardEntry $left, LeaderboardEntry $right): int => $right->getScore() <=> $left->getScore());

        return array_slice($this->entries, 0, $limit);
    }
}
