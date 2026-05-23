<?php

declare(strict_types=1);

namespace Market\Domain\Entity;

final class PricePoint
{
    public function __construct(
        private readonly string $assetId,
        private readonly float $price,
        private readonly int $timestamp
    ) {
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getPrice(): float
    {
        return round($this->price, 2);
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        return [
            'assetId' => $this->assetId,
            'price' => round($this->price, 2),
            'ts' => $this->timestamp,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['assetId'] ?? ''),
            (float) ($data['price'] ?? 0.0),
            (int) ($data['ts'] ?? time())
        );
    }
}
