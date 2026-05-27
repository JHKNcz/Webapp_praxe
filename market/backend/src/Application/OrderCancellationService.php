<?php

declare(strict_types=1);

namespace Market\Application;

use DomainException;

final class OrderCancellationService
{
    public function __construct(
        private readonly object $orderRepository,
        private readonly object $portfolioRepository,
        private readonly ?EventPublisher $eventPublisher = null,
        private readonly ?OrderService $orderService = null
    ) {
    }

    public function cancelAllForSession(string $sessionId): void
    {
        $portfolio = $this->portfolioRepository->find($sessionId);

        if ($portfolio === null) {
            return;
        }

        $assetIds = [];

        foreach ($this->orderRepository->findOpenBySession($sessionId) as $order) {
            $remaining = $order->getRemainingQty();

            $portfolio->releaseOrderCollateral(
                $order->getSide(),
                $order->getAssetId(),
                $remaining,
                $order->getPrice()
            );

            $order->cancel();
            $this->orderRepository->removeFromQueue($order);
            $this->orderRepository->save($order);
            $assetIds[$order->getAssetId()] = true;
        }

        $this->portfolioRepository->save($sessionId, $portfolio);

        if ($this->eventPublisher === null || $this->orderService === null) {
            return;
        }

        foreach (array_keys($assetIds) as $assetId) {
            $this->eventPublisher->publishOrderBook($assetId, $this->orderService->getOrderBook($assetId));
        }
    }

    public function cancelOne(string $sessionId, string $orderId): void
    {
        $order = $this->orderRepository->find($orderId);

        if ($order === null) {
            throw new DomainException('Order not found');
        }

        if ($order->getSessionId() !== $sessionId) {
            throw new DomainException('Order does not belong to this session');
        }

        if (!$order->isOpen()) {
            throw new DomainException('Order is not open');
        }

        $portfolio = $this->portfolioRepository->find($sessionId);

        if ($portfolio === null) {
            throw new DomainException('Portfolio not found');
        }

        $portfolio->releaseOrderCollateral(
            $order->getSide(),
            $order->getAssetId(),
            $order->getRemainingQty(),
            $order->getPrice()
        );

        $order->cancel();
        $this->orderRepository->removeFromQueue($order);
        $this->orderRepository->save($order);
        $this->portfolioRepository->save($sessionId, $portfolio);

        if ($this->eventPublisher !== null && $this->orderService !== null) {
            $this->eventPublisher->publishOrderBook($order->getAssetId(), $this->orderService->getOrderBook($order->getAssetId()));
        }
    }
}
