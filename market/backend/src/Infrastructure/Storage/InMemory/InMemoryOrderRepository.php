<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\InMemory;

use Market\Domain\Entity\Order;

final class InMemoryOrderRepository
{
    /** @var array<string, Order> */
    private array $orders = [];

    /** @var array<string, array<string, list<string>>> */
    private array $queues = [];

    /** @var array<string, list<string>> */
    private array $sessionOrders = [];

    public function enqueue(Order $order): void
    {
        $this->orders[$order->getId()] = $order;
        $queueKey = $this->queueKey($order->getAssetId(), $order->getSide());
        $this->queues[$queueKey][] = $order->getId();
        $this->sessionOrders[$order->getSessionId()][] = $order->getId();
    }

    public function dequeue(string $assetId, string $side): ?Order
    {
        $queueKey = $this->queueKey($assetId, $side);

        while (($this->queues[$queueKey] ?? []) !== []) {
            $orderId = array_shift($this->queues[$queueKey]);
            $order = $this->orders[$orderId] ?? null;

            if ($order === null || !$order->isOpen()) {
                continue;
            }

            return $order;
        }

        return null;
    }

    public function requeueFront(Order $order): void
    {
        $queueKey = $this->queueKey($order->getAssetId(), $order->getSide());
        $this->queues[$queueKey] ??= [];
        array_unshift($this->queues[$queueKey], $order->getId());
    }

    public function requeueBack(Order $order): void
    {
        $queueKey = $this->queueKey($order->getAssetId(), $order->getSide());
        $this->queues[$queueKey] ??= [];
        $this->queues[$queueKey][] = $order->getId();
    }

    public function removeFromQueue(Order $order): void
    {
        $queueKey = $this->queueKey($order->getAssetId(), $order->getSide());
        $ids = $this->queues[$queueKey] ?? [];
        $this->queues[$queueKey] = array_values(array_filter(
            $ids,
            static fn (string $orderId): bool => $orderId !== $order->getId()
        ));
    }

    /** @return array<int, Order> */
    public function listOpenForAsset(string $assetId): array
    {
        $orders = [];

        foreach (['buy', 'sell'] as $side) {
            foreach ($this->queues[$this->queueKey($assetId, $side)] ?? [] as $orderId) {
                $order = $this->orders[$orderId] ?? null;

                if ($order !== null && $order->isOpen()) {
                    $orders[] = $order;
                }
            }
        }

        return $orders;
    }

    public function save(Order $order): void
    {
        $this->orders[$order->getId()] = $order;
    }

    public function find(string $orderId): ?Order
    {
        return $this->orders[$orderId] ?? null;
    }

    /** @return array<int, Order> */
    public function findOpenBySession(string $sessionId): array
    {
        return array_values(array_filter(
            $this->orders,
            static fn (Order $order): bool => $order->getSessionId() === $sessionId && $order->isOpen()
        ));
    }

    /** @return array<int, Order> */
    public function findOpenForAsset(string $assetId): array
    {
        return array_values(array_filter(
            $this->orders,
            static fn (Order $order): bool => $order->getAssetId() === $assetId && $order->isOpen()
        ));
    }

  /** @return array{buy: int, sell: int} */
    public function queueDepth(string $assetId): array
    {
        $buyKey = $this->queueKey($assetId, 'buy');
        $sellKey = $this->queueKey($assetId, 'sell');

        return [
            'buy' => count($this->queues[$buyKey] ?? []),
            'sell' => count($this->queues[$sellKey] ?? []),
        ];
    }

    /** @return array<int, array{side: string, quantity: int}> */
    public function queueSummary(string $assetId): array
    {
        $summary = [];

        foreach (['buy', 'sell'] as $side) {
            $queueKey = $this->queueKey($assetId, $side);

            foreach ($this->queues[$queueKey] ?? [] as $orderId) {
                $order = $this->orders[$orderId] ?? null;

                if ($order === null || !$order->isOpen()) {
                    continue;
                }

                $summary[] = [
                    'side' => $side,
                    'quantity' => $order->getRemainingQty(),
                ];
            }
        }

        return $summary;
    }

    private function queueKey(string $assetId, string $side): string
    {
        return $assetId . ':' . $side;
    }
}
