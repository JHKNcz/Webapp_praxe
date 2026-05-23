<?php

declare(strict_types=1);

namespace Market\Application;

use DomainException;
use Market\Domain\Entity\LeaderboardEntry;
use Market\Infrastructure\Storage\InMemory\LeaderboardRepository;
use Market\Infrastructure\Storage\InMemory\SessionRepository;

final class LeaderboardService
{
    public function __construct(
        private readonly LeaderboardRepository $leaderboardRepository,
        private readonly SessionRepository $sessionRepository,
        private readonly PortfolioService $portfolioService
    ) {
    }

    public function record(string $sessionId, string $displayName = 'Guest'): array
    {
        $session = $this->sessionRepository->find($sessionId);

        if ($session === null) {
            throw new DomainException('Session not found');
        }

        $summary = $this->portfolioService->getPortfolioSummary($sessionId);
        $entry = new LeaderboardEntry($sessionId, $displayName, (float) $summary['totalValue'], time());
        $this->leaderboardRepository->add($entry);

        return $entry->toArray();
    }

    public function top(int $limit = 10): array
    {
        return array_map(
            static fn (LeaderboardEntry $entry): array => $entry->toArray(),
            $this->leaderboardRepository->top($limit)
        );
    }
}
