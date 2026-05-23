<?php

declare(strict_types=1);

namespace Market\Domain\Entity;

final class Order
{
    public function __construct(
        private readonly string $id,
        private readonly string $sessionId,
        private readonly string $assetId,
        private readonly string $side,
        private readonly int $quantity,
        private readonly float $price,
        private readonly int $createdAt,
        private int $remainingQty,
        private string $status = 'open'
    ) {
    }

    public static function create(
        string $sessionId,
        string $assetId,
        string $side,
        int $quantity,
        float $price
    ): self {
        return new self(
            bin2hex(random_bytes(8)),
            $sessionId,
            $assetId,
            $side,
            $quantity,
            $price,
            time(),
            $quantity
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getSide(): string
    {
        return $this->side;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getRemainingQty(): int
    {
        return $this->remainingQty;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function fill(int $quantity): void
    {
        if ($quantity <= 0 || $quantity > $this->remainingQty) {
            throw new \DomainException('Invalid fill quantity');
        }

        $this->remainingQty -= $quantity;
        $this->status = $this->remainingQty === 0 ? 'filled' : 'partial';
    }

    public function isOpen(): bool
    {
        return $this->remainingQty > 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sessionId' => $this->sessionId,
            'assetId' => $this->assetId,
            'side' => $this->side,
            'quantity' => $this->quantity,
            'remainingQty' => $this->remainingQty,
            'price' => $this->price,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
        ];
    }

    /** @param array<string, string> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['sessionId'] ?? ''),
            (string) ($data['assetId'] ?? ''),
            (string) ($data['side'] ?? ''),
            (int) ($data['quantity'] ?? 0),
            (float) ($data['price'] ?? 0.0),
            (int) ($data['createdAt'] ?? time()),
            (int) ($data['remainingQty'] ?? 0),
            (string) ($data['status'] ?? 'open')
        );
    }
}
