<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\Redis;

use Market\Domain\Entity\Order;

final class RedisOrderRepository
{
    public function __construct(private readonly RedisClient $redis)
    {
    }

    public function enqueue(Order $order): void
    {
        $this->save($order);
        $this->redis->lpush($this->queueKey($order->getAssetId(), $order->getSide()), $order->getId());
        $this->redis->sadd('session:' . $order->getSessionId() . ':orders', $order->getId());
    }

    public function dequeue(string $assetId, string $side): ?Order
    {
        while (true) {
            $orderId = $this->redis->rpop($this->queueKey($assetId, $side));

            if ($orderId === null) {
                return null;
            }

            $order = $this->find($orderId);

            if ($order === null || !$order->isOpen()) {
                continue;
            }

            return $order;
        }
    }

    public function requeueFront(Order $order): void
    {
        $this->redis->lpush($this->queueKey($order->getAssetId(), $order->getSide()), $order->getId());
    }

    public function requeueBack(Order $order): void
    {
        $this->redis->rpush($this->queueKey($order->getAssetId(), $order->getSide()), $order->getId());
    }

    public function removeFromQueue(Order $order): void
    {
        $this->redis->lrem($this->queueKey($order->getAssetId(), $order->getSide()), 0, $order->getId());
    }

    /** @return array<int, Order> */
    public function listOpenForAsset(string $assetId): array
    {
        $orders = [];

        foreach (['buy', 'sell'] as $side) {
            foreach ($this->redis->lrange($this->queueKey($assetId, $side), 0, -1) as $orderId) {
                $order = $this->find($orderId);

                if ($order !== null && $order->isOpen()) {
                    $orders[] = $order;
                }
            }
        }

        return $orders;
    }

    public function save(Order $order): void
    {
        $this->redis->hset('order:' . $order->getId(), [
            'id' => $order->getId(),
            'sessionId' => $order->getSessionId(),
            'assetId' => $order->getAssetId(),
            'side' => $order->getSide(),
            'quantity' => $order->getQuantity(),
            'remainingQty' => $order->getRemainingQty(),
            'price' => $order->getPrice(),
            'status' => $order->getStatus(),
            'createdAt' => $order->getCreatedAt(),
        ]);
    }

    public function find(string $orderId): ?Order
    {
        $data = $this->redis->hgetall('order:' . $orderId);

        if ($data === []) {
            return null;
        }

        return Order::fromArray($data);
    }

    /** @return array<int, Order> */
    public function findOpenBySession(string $sessionId): array
    {
        $orders = [];

        foreach ($this->redis->smembers('session:' . $sessionId . ':orders') as $orderId) {
            $order = $this->find($orderId);

            if ($order !== null && $order->isOpen()) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    /** @return array{buy: int, sell: int} */
    public function queueDepth(string $assetId): array
    {
        return [
            'buy' => $this->redis->llen($this->queueKey($assetId, 'buy')),
            'sell' => $this->redis->llen($this->queueKey($assetId, 'sell')),
        ];
    }

    /** @return array<int, array{side: string, quantity: int}> */
    public function queueSummary(string $assetId): array
    {
        $summary = [];

        foreach (['buy', 'sell'] as $side) {
            $orderIds = $this->redis->lrange($this->queueKey($assetId, $side), 0, -1);

            foreach ($orderIds as $orderId) {
                $order = $this->find($orderId);

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
        return 'orders:' . $assetId . ':' . $side;
    }
}
