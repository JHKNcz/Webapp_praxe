<?php

declare(strict_types=1);

namespace Market\Application;

use Market\Domain\Entity\Asset;
use Market\Domain\Entity\PricePoint;

final class AssetService
{
    public function __construct(
        private readonly object $assetRepository,
        private readonly object $priceHistoryRepository,
        private readonly PriceGeneratorService $priceGeneratorService,
        private readonly int $priceUpdateIntervalSeconds,
        private readonly int $historyLimit,
        private readonly bool $debug,
        private readonly ?EventPublisher $eventPublisher = null,
        private readonly ?LeaderboardService $leaderboardService = null,
        private readonly ?ActiveSessionRegistry $activeSessionRegistry = null
    ) {
    }

    public function listAssets(): array
    {
        $this->refreshMarket();

        return array_map(static fn (Asset $asset): array => $asset->toArray(), $this->assetRepository->all());
    }

    public function tick(): array
    {
        $this->refreshMarket(true);
        $items = array_map(static fn (Asset $asset): array => $asset->toArray(), $this->assetRepository->all());
        $this->eventPublisher?->publishPriceTick($items);

        return $items;
    }

    public function getAsset(string $assetId): ?array
    {
        $this->refreshMarket();

        $asset = $this->assetRepository->find($assetId);

        return $asset?->toArray();
    }

    public function getHistory(string $assetId, ?int $limit = null): array
    {
        $this->refreshMarket();

        return array_map(
            static fn (PricePoint $pricePoint): array => $pricePoint->toArray(),
            $this->priceHistoryRepository->history($assetId, $limit ?? $this->historyLimit)
        );
    }

    public function getCurrentPrices(): array
    {
        $this->refreshMarket();

        $prices = [];
        foreach ($this->assetRepository->all() as $asset) {
            $prices[$asset->getId()] = $asset->getLastPrice();
        }

        return $prices;
    }

    private function refreshMarket(bool $forceTick = false): void
    {
        $updated = false;

        foreach ($this->assetRepository->all() as $asset) {
            $lastPricePoint = $this->priceHistoryRepository->last($asset->getId());

            if ($lastPricePoint === null) {
                $initial = new PricePoint($asset->getId(), $asset->getLastPrice(), time());
                $this->priceHistoryRepository->append($initial);
                $lastPricePoint = $initial;

                if (!$forceTick) {
                    continue;
                }
            }

            if (!$forceTick && (time() - $lastPricePoint->getTimestamp()) < $this->priceUpdateIntervalSeconds) {
                continue;
            }

            $nextPricePoint = $this->priceGeneratorService->nextPrice($asset);
            $this->priceHistoryRepository->append($nextPricePoint);
            $asset->setLastPrice($nextPricePoint->getPrice());
            $this->assetRepository->save($asset);
            $updated = true;

            if ($this->debug) {
                error_log(json_encode([
                    'type' => 'price_tick',
                    'assetId' => $asset->getId(),
                    'price' => $nextPricePoint->getPrice(),
                    'ts' => $nextPricePoint->getTimestamp(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
            }
        }

        if ($updated && $this->eventPublisher !== null) {
            $items = array_map(static fn (Asset $asset): array => $asset->toArray(), $this->assetRepository->all());
            $this->eventPublisher->publishPriceTick($items);

            if ($this->leaderboardService !== null && $this->activeSessionRegistry !== null) {
                $this->leaderboardService->refreshAllActive($this->activeSessionRegistry);
            }
        }
    }
}
