<?php

declare(strict_types=1);

namespace Market\Domain\Entity;

final class Trade
{
    public function __construct(
        private readonly string $id,
        private readonly string $buyOrderId,
        private readonly string $sellOrderId,
        private readonly string $buySessionId,
        private readonly string $sellSessionId,
        private readonly string $assetId,
        private readonly float $price,
        private readonly int $quantity,
        private readonly int $timestamp
    ) {
    }

    public static function create(
        Order $buyOrder,
        Order $sellOrder,
        float $price,
        int $quantity
    ): self {
        return new self(
            bin2hex(random_bytes(8)),
            $buyOrder->getId(),
            $sellOrder->getId(),
            $buyOrder->getSessionId(),
            $sellOrder->getSessionId(),
            $buyOrder->getAssetId(),
            $price,
            $quantity,
            time()
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'buyOrderId' => $this->buyOrderId,
            'sellOrderId' => $this->sellOrderId,
            'buySessionId' => $this->buySessionId,
            'sellSessionId' => $this->sellSessionId,
            'assetId' => $this->assetId,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'timestamp' => $this->timestamp,
        ];
    }
}
