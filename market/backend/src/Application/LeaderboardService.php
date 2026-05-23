<?php

declare(strict_types=1);

namespace Market\Application;

use DomainException;
use Market\Domain\Entity\LeaderboardEntry;

final class LeaderboardService
{
    public function __construct(
        private readonly object $leaderboardRepository,
        private readonly object $sessionRepository,
        private readonly PortfolioService $portfolioService,
        private readonly EventPublisher $eventPublisher,
        private readonly int $leaderboardLimit = 10
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

    public function syncLive(string $sessionId): void
    {
        $session = $this->sessionRepository->find($sessionId);

        if ($session === null || !$session->isActive()) {
            return;
        }

        $summary = $this->portfolioService->getPortfolioSummary($sessionId);
        $entry = new LeaderboardEntry(
            $sessionId,
            $session->getNickname(),
            (float) $summary['totalValue'],
            time()
        );
        $this->leaderboardRepository->upsertLive($entry);
        $this->publishLiveTop();
    }

    public function removeLive(string $sessionId, string $displayName = ''): void
    {
        $this->leaderboardRepository->removeLive($sessionId, $displayName);
        $this->publishLiveTop();
    }

    public function refreshAllActive(ActiveSessionRegistry $registry): void
    {
        foreach ($registry->all() as $sessionId) {
            $this->syncLive($sessionId);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function top(int $limit = 10): array
    {
        return array_map(
            static fn (LeaderboardEntry $entry): array => $entry->toArray(),
            $this->leaderboardRepository->topLive($limit)
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function topHallOfFame(int $limit = 10): array
    {
        return array_map(
            static fn (LeaderboardEntry $entry): array => $entry->toArray(),
            $this->leaderboardRepository->top($limit)
        );
    }

    private function publishLiveTop(): void
    {
        $top = $this->top($this->leaderboardLimit);
        $this->eventPublisher->publishLeaderboard($top);
    }
}
