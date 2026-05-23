<?php

declare(strict_types=1);

namespace Market\Application;

use DomainException;

/**
 * Executes trades against the simulated market (instant fill at lastPrice).
 */
final class MarketTradeService
{
    public function __construct(
        private readonly object $sessionRepository,
        private readonly object $portfolioRepository,
        private readonly PortfolioService $portfolioService,
        private readonly ?TradeHistoryService $tradeHistoryService = null
    ) {
    }

    /** @return array<string, mixed> */
    public function execute(string $sessionId, string $assetId, string $side, int $quantity, float $price): array
    {
        $this->assertActiveSession($sessionId);

        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero');
        }

        $portfolio = $this->portfolioService->getPortfolio($sessionId);

        if ($side === 'buy') {
            $portfolio->buy($assetId, $quantity, $price);
        } elseif ($side === 'sell') {
            $portfolio->sell($assetId, $quantity, $price);
        } else {
            throw new DomainException('Side must be buy or sell');
        }

        $this->portfolioRepository->save($sessionId, $portfolio);

        $trade = [
            'id' => bin2hex(random_bytes(8)),
            'type' => 'market',
            'source' => 'exchange',
            'assetId' => $assetId,
            'side' => $side,
            'quantity' => $quantity,
            'price' => $price,
            'sessionId' => $sessionId,
            'timestamp' => time(),
        ];

        $this->tradeHistoryService?->recordMarketTrade($trade);

        return $trade;
    }

    private function assertActiveSession(string $sessionId): void
    {
        $session = $this->sessionRepository->find($sessionId);

        if ($session === null) {
            throw new DomainException('Session not found');
        }

        if (!$session->isActive()) {
            throw new DomainException('Session is already closed');
        }
    }
}
