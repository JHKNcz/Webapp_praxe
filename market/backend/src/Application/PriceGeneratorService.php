<?php

declare(strict_types=1);

namespace Market\Application;

use Market\Domain\Entity\Asset;
use Market\Domain\Entity\PricePoint;

final class PriceGeneratorService
{
    public function nextPrice(Asset $asset): PricePoint
    {
        $currentPrice = $asset->getLastPrice();
        $fairPrice = $asset->getFairPrice() + $asset->getTrendSlope();
        $asset->setFairPrice($fairPrice);

        $reversionStrength = 0.2;
        $reversion = ($fairPrice - $currentPrice) * $reversionStrength;

        $noisePercent = $asset->getRisk() * 0.05; // up to ±5% per tick at risk=1
        $noise = 0.0;

        if ($noisePercent > 0.0) {
            $scale = 10000;
            $min = (int) round(-$noisePercent * $scale);
            $max = (int) round($noisePercent * $scale);
            $noiseFactor = random_int($min, $max) / $scale;
            $noise = $currentPrice * $noiseFactor;
        }

        $nextPrice = round(max(1.0, $currentPrice + $reversion + $noise), 2);

        return new PricePoint(
            $asset->getId(),
            $nextPrice,
            time()
        );
    }
}
