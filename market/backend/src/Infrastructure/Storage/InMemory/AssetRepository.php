<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\InMemory;

use Market\Domain\Entity\Asset;

final class AssetRepository
{
    /** @var array<string, Asset> */
    private array $assets = [];

    public function __construct(array $seedAssets = [])
    {
        if ($seedAssets === []) {
            $seedAssets = [
                [
                    'id' => 'asset-1',
                    'name' => 'Alpha Token',
                    'lastPrice' => 100.0,
                ],
            ];
        }

        foreach ($seedAssets as $seedAsset) {
            $lastPrice = (float) $seedAsset['lastPrice'];
            $fairPrice = array_key_exists('fairPrice', $seedAsset) ? (float) $seedAsset['fairPrice'] : $lastPrice;
            $risk = array_key_exists('risk', $seedAsset) ? (float) $seedAsset['risk'] : 0.2;
            $trendSlope = array_key_exists('trendSlope', $seedAsset) ? (float) $seedAsset['trendSlope'] : 0.0;

            $asset = new Asset(
                (string) $seedAsset['id'],
                (string) $seedAsset['name'],
                $lastPrice,
                $fairPrice,
                $risk,
                $trendSlope
            );

            $this->assets[$asset->getId()] = $asset;
        }
    }

    /** @return array<int, Asset> */
    public function all(): array
    {
        return array_values($this->assets);
    }

    public function find(string $id): ?Asset
    {
        return $this->assets[$id] ?? null;
    }

    public function save(Asset $asset): void
    {
        $this->assets[$asset->getId()] = $asset;
    }
}
