<?php

declare(strict_types=1);

namespace Market\Application;

use DomainException;
use Market\Domain\Entity\Order;
use Market\Infrastructure\Storage\InMemory\PortfolioRepository;
use Market\Infrastructure\Storage\InMemory\SessionRepository;

final class OrderService
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
        private readonly PortfolioRepository $portfolioRepository,
        private readonly AssetService $assetService,
        private readonly PortfolioService $portfolioService,
        private readonly object $orderRepository,
        private readonly MatchingEngine $matchingEngine
    ) {
    }

    public function placeOrder(string $sessionId, string $assetId, string $side, int $quantity): array
    {
        $this->assertActiveSession($sessionId);

        if (!in_array($side, ['buy', 'sell'], true)) {
            throw new DomainException('Side must be buy or sell');
        }

        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero');
        }

        $asset = $this->assetService->getAsset($assetId);

        if ($asset === null) {
            throw new DomainException('Asset not found');
        }

        $price = (float) $asset['lastPrice'];
        $portfolio = $this->portfolioService->getPortfolio($sessionId);

        if ($side === 'buy') {
            $portfolio->lockCash(round($quantity * $price, 2));
        } else {
            $portfolio->lockShares($assetId, $quantity);
        }

        $this->portfolioRepository->save($sessionId, $portfolio);

        $order = Order::create($sessionId, $assetId, $side, $quantity, $price);
        $this->orderRepository->enqueue($order);

        $trades = $this->matchingEngine->match($assetId, $price);

        $savedOrder = $this->orderRepository->find($order->getId()) ?? $order;

        return [
            'order' => $savedOrder->toArray(),
            'trades' => $trades,
            'portfolio' => $this->portfolioService->getPortfolioSummary($sessionId),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getOpenOrders(string $sessionId): array
    {
        $this->assertActiveSession($sessionId);

        return array_map(
            static fn (Order $order): array => $order->toArray(),
            $this->orderRepository->findOpenBySession($sessionId)
        );
    }

    public function getOrderBook(string $assetId): array
    {
        $asset = $this->assetService->getAsset($assetId);

        if ($asset === null) {
            throw new DomainException('Asset not found');
        }

        return [
            'assetId' => $assetId,
            'lastPrice' => $asset['lastPrice'],
            'depth' => $this->orderRepository->queueDepth($assetId),
            'queue' => $this->orderRepository->queueSummary($assetId),
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
