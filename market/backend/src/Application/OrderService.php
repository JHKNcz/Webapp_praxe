<?php

declare(strict_types=1);

namespace Market\Application;

use DomainException;
use Market\Domain\Entity\Order;

final class OrderService
{
    public function __construct(
        private readonly object $sessionRepository,
        private readonly object $portfolioRepository,
        private readonly AssetService $assetService,
        private readonly PortfolioService $portfolioService,
        private readonly MarketTradeService $marketTradeService,
        private readonly object $orderRepository,
        private readonly MatchingEngine $matchingEngine,
        private readonly LeaderboardService $leaderboardService,
        private readonly EventPublisher $eventPublisher
    ) {
    }

    public function placeOrder(string $sessionId, string $assetId, string $side, int $quantity, string $mode = 'market'): array
    {
        if ($mode === 'limit') {
            return $this->postLimitOrder($sessionId, $assetId, $side, $quantity);
        }

        return $this->placeMarketOrder($sessionId, $assetId, $side, $quantity);
    }

    public function placeMarketOrder(string $sessionId, string $assetId, string $side, int $quantity): array
    {
        $this->assertActiveSession($sessionId);
        $this->validateSideAndQuantity($side, $quantity);

        $asset = $this->requireAsset($assetId);
        $price = (float) $asset['lastPrice'];
        $trade = $this->marketTradeService->execute($sessionId, $assetId, $side, $quantity, $price);
        $this->eventPublisher->publishTrade($trade);
        $this->leaderboardService->syncLive($sessionId);

        return [
            'fillType' => 'market',
            'trades' => [$trade],
            'portfolio' => $this->portfolioService->getPortfolioSummary($sessionId),
        ];
    }

    public function postLimitOrder(string $sessionId, string $assetId, string $side, int $quantity): array
    {
        $this->assertActiveSession($sessionId);
        $this->validateSideAndQuantity($side, $quantity);

        $asset = $this->requireAsset($assetId);
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

        foreach (array_unique(array_merge(
            [$sessionId],
            array_map(static fn (array $trade): string => (string) ($trade['buySessionId'] ?? ''), $trades),
            array_map(static fn (array $trade): string => (string) ($trade['sellSessionId'] ?? ''), $trades)
        )) as $activeSessionId) {
            if ($activeSessionId !== '') {
                $this->leaderboardService->syncLive($activeSessionId);
            }
        }

        $this->publishOrderBook($assetId);

        return [
            'fillType' => 'limit',
            'order' => $savedOrder->toArray(),
            'trades' => $trades,
            'portfolio' => $this->portfolioService->getPortfolioSummary($sessionId),
        ];
    }

    public function takeOrder(string $takerSessionId, string $orderId, int $quantity): array
    {
        $this->assertActiveSession($takerSessionId);

        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero');
        }

        $makerOrder = $this->orderRepository->find($orderId);

        if ($makerOrder === null) {
            throw new DomainException('Order not found');
        }

        $trade = $this->matchingEngine->takeOrder($takerSessionId, $orderId, $quantity);
        $this->leaderboardService->syncLive($takerSessionId);
        $this->leaderboardService->syncLive($makerOrder->getSessionId());
        $this->publishOrderBook($makerOrder->getAssetId());

        return [
            'fillType' => 'p2p',
            'trades' => [$trade],
            'portfolio' => $this->portfolioService->getPortfolioSummary($takerSessionId),
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
        $asset = $this->requireAsset($assetId);
        $bids = [];
        $asks = [];

        foreach ($this->orderRepository->listOpenForAsset($assetId) as $order) {
            $row = $this->orderToBookRow($order);

            if ($order->getSide() === 'buy') {
                $bids[] = $row;
            } else {
                $asks[] = $row;
            }
        }

        usort($bids, static fn (array $a, array $b): int => $b['price'] <=> $a['price']);
        usort($asks, static fn (array $a, array $b): int => $a['price'] <=> $b['price']);

        return [
            'assetId' => $assetId,
            'lastPrice' => $asset['lastPrice'],
            'depth' => $this->orderRepository->queueDepth($assetId),
            'bids' => $bids,
            'asks' => $asks,
        ];
    }

    /** @return array<string, mixed> */
    private function orderToBookRow(Order $order): array
    {
        $session = $this->sessionRepository->find($order->getSessionId());

        return [
            'id' => $order->getId(),
            'side' => $order->getSide(),
            'price' => $order->getPrice(),
            'remainingQty' => $order->getRemainingQty(),
            'nickname' => $session?->getNickname() ?? 'Guest',
            'sessionId' => $order->getSessionId(),
            'createdAt' => $order->getCreatedAt(),
        ];
    }

    private function publishOrderBook(string $assetId): void
    {
        $this->eventPublisher->publishOrderBook($assetId, $this->getOrderBook($assetId));
    }

    /** @return array<string, mixed> */
    private function requireAsset(string $assetId): array
    {
        $asset = $this->assetService->getAsset($assetId);

        if ($asset === null) {
            throw new DomainException('Asset not found');
        }

        return $asset;
    }

    private function validateSideAndQuantity(string $side, int $quantity): void
    {
        if (!in_array($side, ['buy', 'sell'], true)) {
            throw new DomainException('Side must be buy or sell');
        }

        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero');
        }
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
