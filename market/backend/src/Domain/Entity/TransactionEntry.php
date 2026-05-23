<?php

declare(strict_types=1);

namespace Market\Domain\Entity;

final class TransactionEntry
{
    public function __construct(
        private readonly string $id,
        private readonly string $sessionId,
        private readonly string $side,
        private readonly string $assetId,
        private readonly int $quantity,
        private readonly float $price,
        private readonly string $type,
        private readonly string $counterparty,
        private readonly int $timestamp
    ) {
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getTotal(): float
    {
        return round($this->quantity * $this->price, 2);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sessionId' => $this->sessionId,
            'side' => $this->side,
            'assetId' => $this->assetId,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'total' => $this->getTotal(),
            'type' => $this->type,
            'counterparty' => $this->counterparty,
            'timestamp' => $this->timestamp,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['sessionId'] ?? ''),
            (string) ($data['side'] ?? ''),
            (string) ($data['assetId'] ?? ''),
            (int) ($data['quantity'] ?? 0),
            (float) ($data['price'] ?? 0.0),
            (string) ($data['type'] ?? 'market'),
            (string) ($data['counterparty'] ?? ''),
            (int) ($data['timestamp'] ?? time())
        );
    }
}
