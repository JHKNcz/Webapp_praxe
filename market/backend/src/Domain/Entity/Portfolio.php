<?php

declare(strict_types=1);

namespace Market\Domain\Entity;

use DomainException;

final class Portfolio
{
    /** @var array<string, Holding> */
    private array $holdings = [];

    public function __construct(private float $cash)
    {
        $this->cash = round(max(0.0, $cash), 2);
    }

    public function getCash(): float
    {
        return round($this->cash, 2);
    }

    public function setCash(float $cash): void
    {
        $this->cash = round(max(0.0, $cash), 2);
    }

    public function lockCash(float $amount): void
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new DomainException('Amount must be greater than zero');
        }

        if ($amount > $this->cash) {
            throw new DomainException('Not enough cash');
        }

        $this->cash = round($this->cash - $amount, 2);
    }

    public function creditCash(float $amount): void
    {
        $this->cash = round($this->cash + max(0.0, $amount), 2);
    }

    public function lockShares(string $assetId, int $quantity): void
    {
        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero');
        }

        if (!isset($this->holdings[$assetId])) {
            throw new DomainException('Holding not found');
        }

        $holding = $this->holdings[$assetId];
        $holding->decrease($quantity);

        if ($holding->isEmpty()) {
            unset($this->holdings[$assetId]);
        }
    }

    public function creditShares(string $assetId, int $quantity, float $price): void
    {
        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero');
        }

        if (!isset($this->holdings[$assetId])) {
            $this->holdings[$assetId] = new Holding($assetId, $quantity, $price);
            return;
        }

        $this->holdings[$assetId]->increase($quantity, $price);
    }

    public function getHolding(string $assetId): ?Holding
    {
        return $this->holdings[$assetId] ?? null;
    }

    /** @return array<int, Holding> */
    public function getHoldings(): array
    {
        return array_values($this->holdings);
    }

    public function buy(string $assetId, int $quantity, float $price): void
    {
        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero');
        }

        $cost = round($quantity * $price, 2);

        if ($cost > $this->cash) {
            throw new DomainException('Not enough cash');
        }

        $this->cash = round($this->cash - $cost, 2);

        if (!isset($this->holdings[$assetId])) {
            $this->holdings[$assetId] = new Holding($assetId, $quantity, $price);
            return;
        }

        $this->holdings[$assetId]->increase($quantity, $price);
    }

    public function sell(string $assetId, int $quantity, float $price): void
    {
        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero');
        }

        if (!isset($this->holdings[$assetId])) {
            throw new DomainException('Holding not found');
        }

        $holding = $this->holdings[$assetId];
        $holding->decrease($quantity);

        $this->cash = round($this->cash + ($quantity * $price), 2);

        if ($holding->isEmpty()) {
            unset($this->holdings[$assetId]);
        }
    }

    /** @param array<string, float> $currentPrices */
    public function getTotalValue(array $currentPrices): float
    {
        $total = $this->cash;

        foreach ($this->holdings as $holding) {
            $currentPrice = $currentPrices[$holding->getAssetId()] ?? 0.0;
            $total += $holding->getQuantity() * $currentPrice;
        }

        return round($total, 2);
    }

    public function toArray(): array
    {
        return [
            'cash' => $this->getCash(),
            'holdings' => array_map(static fn (Holding $holding): array => $holding->toArray(), $this->getHoldings()),
        ];
    }
}
