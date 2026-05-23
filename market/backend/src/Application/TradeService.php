<?php

declare(strict_types=1);

namespace Market\Application;

use DomainException;
use Market\Infrastructure\Storage\InMemory\PortfolioRepository;
use Market\Infrastructure\Storage\InMemory\SessionRepository;

final class TradeService
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
        private readonly PortfolioRepository $portfolioRepository,
        private readonly AssetService $assetService,
        private readonly PortfolioService $portfolioService
    ) {
    }

    public function buy(string $sessionId, string $assetId, int $quantity): array
    {
        $this->assertActiveSession($sessionId);

        $asset = $this->assetService->getAsset($assetId);

        if ($asset === null) {
            throw new DomainException('Asset not found');
        }

        $portfolio = $this->portfolioService->getPortfolio($sessionId);
        $price = (float) $asset['lastPrice'];
        $portfolio->buy($assetId, $quantity, $price);
        $this->portfolioRepository->save($sessionId, $portfolio);

        return [
            'type' => 'buy',
            'assetId' => $assetId,
            'quantity' => $quantity,
            'price' => $price,
            'portfolio' => $this->portfolioService->getPortfolioSummary($sessionId),
        ];
    }

    public function sell(string $sessionId, string $assetId, int $quantity): array
    {
        $this->assertActiveSession($sessionId);

        $asset = $this->assetService->getAsset($assetId);

        if ($asset === null) {
            throw new DomainException('Asset not found');
        }

        $portfolio = $this->portfolioService->getPortfolio($sessionId);
        $price = (float) $asset['lastPrice'];
        $portfolio->sell($assetId, $quantity, $price);
        $this->portfolioRepository->save($sessionId, $portfolio);

        return [
            'type' => 'sell',
            'assetId' => $assetId,
            'quantity' => $quantity,
            'price' => $price,
            'portfolio' => $this->portfolioService->getPortfolioSummary($sessionId),
        ];
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
