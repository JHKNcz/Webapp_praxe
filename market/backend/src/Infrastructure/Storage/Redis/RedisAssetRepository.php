<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\Redis;

use Market\Domain\Entity\Asset;

final class RedisAssetRepository
{
    public function __construct(
        private readonly RedisClient $redis,
        private readonly array $seedAssets = []
    ) {
        $this->ensureSeeded();
    }

    /** @return array<int, Asset> */
    public function all(): array
    {
        $assets = [];

        foreach ($this->redis->smembers('assets:index') as $assetId) {
            $asset = $this->find($assetId);

            if ($asset !== null) {
                $assets[] = $asset;
            }
        }

        return $assets;
    }

    public function find(string $id): ?Asset
    {
        $data = $this->redis->hgetall('asset:' . $id);

        if ($data === []) {
            return null;
        }

        return $this->fromHash($data);
    }

    public function save(Asset $asset): void
    {
        $this->redis->hset('asset:' . $asset->getId(), [
            'id' => $asset->getId(),
            'name' => $asset->getName(),
            'lastPrice' => $asset->getLastPrice(),
            'fairPrice' => $asset->getFairPrice(),
            'risk' => $asset->getRisk(),
            'trendSlope' => $asset->getTrendSlope(),
            'currentSlope' => $asset->getCurrentSlope(),
            'phase' => $asset->getPhase(),
            'phaseTicksRemaining' => $asset->getPhaseTicksRemaining(),
            'phaseTotalDuration' => $asset->getPhaseTotalDuration(),
        ]);
        $this->redis->sadd('assets:index', $asset->getId());
    }

    private function ensureSeeded(): void
    {
        $seeds = $this->seedAssets !== [] ? $this->seedAssets : [
            ['id' => 'asset-1', 'name' => 'Alpha Token', 'lastPrice' => 100.0],
        ];

        foreach ($seeds as $seedAsset) {
            $id = (string) $seedAsset['id'];
            // Only seed assets that don't exist yet — additive, never overwrites live data
            if ($this->redis->smembers('assets:index') !== [] && $this->find($id) !== null) {
                continue;
            }
            $lastPrice = (float) $seedAsset['lastPrice'];
            $asset = new Asset(
                $id,
                (string) $seedAsset['name'],
                $lastPrice,
                array_key_exists('fairPrice', $seedAsset) ? (float) $seedAsset['fairPrice'] : $lastPrice,
                array_key_exists('risk', $seedAsset) ? (float) $seedAsset['risk'] : 0.2,
                array_key_exists('trendSlope', $seedAsset) ? (float) $seedAsset['trendSlope'] : 0.0
            );
            $this->save($asset);
        }
    }

    /** @param array<string, string> $data */
    private function fromHash(array $data): Asset
    {
        return new Asset(
            (string) ($data['id'] ?? ''),
            (string) ($data['name'] ?? ''),
            (float) ($data['lastPrice'] ?? 0.0),
            (float) ($data['fairPrice'] ?? 0.0),
            (float) ($data['risk'] ?? 0.2),
            (float) ($data['trendSlope'] ?? 0.0),
            (float) ($data['currentSlope'] ?? 0.0),
            (string) ($data['phase'] ?? 'normal'),
            (int) ($data['phaseTicksRemaining'] ?? 0),
            (int) ($data['phaseTotalDuration'] ?? 0),
        );
    }
}
