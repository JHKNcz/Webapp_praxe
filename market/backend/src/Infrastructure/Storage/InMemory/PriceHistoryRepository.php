<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\InMemory;

use Market\Domain\Entity\PricePoint;

final class PriceHistoryRepository
{
    /** @var array<string, array<int, PricePoint>> */
    private array $histories = [];

    public function append(PricePoint $pricePoint): void
    {
        $this->histories[$pricePoint->getAssetId()][] = $pricePoint;
    }

    public function last(string $assetId): ?PricePoint
    {
        if (!isset($this->histories[$assetId]) || $this->histories[$assetId] === []) {
            return null;
        }

        return $this->histories[$assetId][array_key_last($this->histories[$assetId])];
    }

    /** @return array<int, PricePoint> */
    public function history(string $assetId, int $limit = 20): array
    {
        $history = $this->histories[$assetId] ?? [];

        if ($limit > 0) {
            $history = array_slice($history, -$limit);
        }

        return $history;
    }
}
