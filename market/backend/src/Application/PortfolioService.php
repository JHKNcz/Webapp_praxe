<?php

declare(strict_types=1);

namespace Market\Application;

use DomainException;
use Market\Domain\Entity\Portfolio;
use Market\Infrastructure\Storage\InMemory\PortfolioRepository;
use Market\Infrastructure\Storage\InMemory\SessionRepository;

final class PortfolioService
{
    public function __construct(
        private readonly PortfolioRepository $portfolioRepository,
        private readonly SessionRepository $sessionRepository,
        private readonly AssetService $assetService
    ) {
    }

    public function createPortfolio(string $sessionId, float $cash): Portfolio
    {
        $portfolio = new Portfolio($cash);
        $this->portfolioRepository->save($sessionId, $portfolio);

        return $portfolio;
    }

    public function getPortfolio(string $sessionId): Portfolio
    {
        $session = $this->sessionRepository->find($sessionId);

        if ($session === null) {
            throw new DomainException('Session not found');
        }

        $portfolio = $this->portfolioRepository->find($sessionId);

        if ($portfolio === null) {
            throw new DomainException('Portfolio not found');
        }

        return $portfolio;
    }

    public function getPortfolioSummary(string $sessionId): array
    {
        $portfolio = $this->getPortfolio($sessionId);
        $prices = $this->assetService->getCurrentPrices();
        $holdings = [];

        foreach ($portfolio->getHoldings() as $holding) {
            $currentPrice = $prices[$holding->getAssetId()] ?? 0.0;

            $holdings[] = [
                'assetId' => $holding->getAssetId(),
                'quantity' => $holding->getQuantity(),
                'averagePrice' => $holding->getAveragePrice(),
                'currentPrice' => $currentPrice,
                'currentValue' => round($holding->getQuantity() * $currentPrice, 2),
            ];
        }

        return [
            'sessionId' => $sessionId,
            'cash' => $portfolio->getCash(),
            'holdings' => $holdings,
            'totalValue' => $portfolio->getTotalValue($prices),
        ];
    }
}
