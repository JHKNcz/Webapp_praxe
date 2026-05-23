<?php

declare(strict_types=1);

namespace Market\Application;

use DomainException;
use Market\Domain\Entity\Portfolio;

final class PortfolioService
{
    public function __construct(
        private readonly object $portfolioRepository,
        private readonly object $sessionRepository,
        private readonly AssetService $assetService,
        private readonly float $initialCash = 10000.0
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
            $averagePrice = $holding->getAveragePrice();
            $quantity = $holding->getQuantity();
            $unrealizedPnl = round(($currentPrice - $averagePrice) * $quantity, 2);

            $holdings[] = [
                'assetId' => $holding->getAssetId(),
                'quantity' => $quantity,
                'averagePrice' => $averagePrice,
                'currentPrice' => $currentPrice,
                'currentValue' => round($quantity * $currentPrice, 2),
                'unrealizedPnl' => $unrealizedPnl,
            ];
        }

        $totalValue = $portfolio->getTotalValue($prices);
        $pnl = round($totalValue - $this->initialCash, 2);
        $pnlPercent = $this->initialCash > 0
            ? round(($pnl / $this->initialCash) * 100, 2)
            : 0.0;

        return [
            'sessionId' => $sessionId,
            'cash' => $portfolio->getCash(),
            'holdings' => $holdings,
            'totalValue' => $totalValue,
            'initialCash' => $this->initialCash,
            'pnl' => $pnl,
            'pnlPercent' => $pnlPercent,
        ];
    }
}
