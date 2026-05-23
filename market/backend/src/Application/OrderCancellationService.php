<?php

declare(strict_types=1);

namespace Market\Application;

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
}
