<?php

declare(strict_types=1);

namespace Market\Domain\Entity;

use DomainException;

final class Holding
{
    public function __construct(
        private readonly string $assetId,
        private int $quantity,
        private float $averagePrice
    ) {
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getAveragePrice(): float
    {
        return round($this->averagePrice, 2);
    }

    public function increase(int $quantity, float $price): void
    {
        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero');
        }

        $totalCost = ($this->quantity * $this->averagePrice) + ($quantity * $price);
        $this->quantity += $quantity;
        $this->averagePrice = $totalCost / $this->quantity;
    }

    public function decrease(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero');
        }

        if ($quantity > $this->quantity) {
            throw new DomainException('Not enough quantity to sell');
        }

        $this->quantity -= $quantity;
    }

    public function isEmpty(): bool
    {
        return $this->quantity === 0;
    }

    public function toArray(): array
    {
        return [
            'assetId' => $this->assetId,
            'quantity' => $this->quantity,
            'averagePrice' => round($this->averagePrice, 2),
        ];
    }
}
