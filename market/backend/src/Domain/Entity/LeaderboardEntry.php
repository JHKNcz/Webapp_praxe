<?php

declare(strict_types=1);

namespace Market\Domain\Entity;

final class LeaderboardEntry
{
    public function __construct(
        private readonly string $sessionId,
        private readonly string $displayName,
        private readonly float $score,
        private readonly int $createdAt
    ) {
    }

    public function getScore(): float
    {
        return round($this->score, 2);
    }

    public function toArray(): array
    {
        return [
            'sessionId' => $this->sessionId,
            'displayName' => $this->displayName,
            'score' => round($this->score, 2),
            'createdAt' => $this->createdAt,
        ];
    }
}
