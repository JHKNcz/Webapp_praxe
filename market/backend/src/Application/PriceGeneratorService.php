<?php
declare(strict_types=1);
namespace Market\Application;

use Market\Domain\Entity\Asset;
use Market\Domain\Entity\PricePoint;

final class PriceGeneratorService
{
    private const EVENTS = [
        'bull_run' => [
            'noiseMod'  => 2.0,
            'duration'  => [20, 35],
            'headlines' => [
                '%s announces breakthrough quarterly results — shares surge',
                '%s secures major strategic partnership',
                '%s share buyback programme confirmed — investor confidence soars',
            ],
        ],
        'bear_crash' => [
            'noiseMod'  => 2.5,
            'duration'  => [15, 25],
            'headlines' => [
                '%s reports "minor liquidity concerns" in regulatory filing',
                '%s CEO steps down with immediate effect',
                '%s auditor declines to sign off on annual accounts',
            ],
        ],
        'pump_dump' => [
            'noiseMod'  => 1.5,
            'duration'  => [30, 45],
            'headlines' => [
                'Unusual trading volume detected in %s — market surveillance notified',
                '%s trending on financial forums; analysts remain cautious',
                'Retail investors accumulate %s positions on unverified rumours',
            ],
        ],
    ];

    /** @return array{pricePoint: PricePoint, event: ?array} */
    public function nextPrice(Asset $asset): array
    {
        $this->advancePhase($asset);
        $event = $this->maybeFireEvent($asset);

        $currentPrice = $asset->getLastPrice();

        // Dynamic slope: mean-reverting random walk
        $slopeDrift  = $asset->getRisk() * 0.008;
        $randFactor  = random_int(-10000, 10000) / 10000;
        $newSlope    = $asset->getCurrentSlope() * 0.97
                     + $asset->getTrendSlope()   * 0.03
                     + $slopeDrift * $randFactor;
        $asset->setCurrentSlope($newSlope);

        // Fair price advance with phase slope modifier
        $slopeMod  = $this->getPhaseSlopeMod($asset);
        $fairPrice = $asset->getFairPrice() + $newSlope * $slopeMod;
        $asset->setFairPrice($fairPrice);

        // Mean reversion toward fair price
        $reversion = ($fairPrice - $currentPrice) * 0.15;

        // Scaled noise (tuned for 2 ticks/s — was 0.08, now 0.025)
        $noiseMod    = $this->getPhaseNoiseMod($asset);
        $noisePercent = $asset->getRisk() * 0.025 * $noiseMod;
        $noise = 0.0;
        if ($noisePercent > 0.0) {
            $scale      = 10000;
            $min        = (int) round(-$noisePercent * $scale);
            $max        = (int) round($noisePercent  * $scale);
            $noiseFactor = random_int($min, $max) / $scale;
            $noise      = $currentPrice * $noiseFactor;
        }

        $nextPrice = round(max(1.0, $currentPrice + $reversion + $noise), 2);

        return [
            'pricePoint' => new PricePoint($asset->getId(), $nextPrice, time()),
            'event'      => $event,
        ];
    }

    private function advancePhase(Asset $asset): void
    {
        if ($asset->getPhase() === 'normal') {
            return;
        }
        $remaining = $asset->getPhaseTicksRemaining() - 1;
        if ($remaining <= 0) {
            $asset->setPhase('normal', 0);
        } else {
            $asset->setPhaseTicksRemaining($remaining);
        }
    }

    private function maybeFireEvent(Asset $asset): ?array
    {
        // ~0.6% per tick ≈ one event per asset per ~85 s at 2 ticks/s
        if ($asset->getPhase() !== 'normal' || random_int(0, 999) >= 6) {
            return null;
        }
        $keys   = array_keys(self::EVENTS);
        $key    = $keys[random_int(0, count($keys) - 1)];
        $def    = self::EVENTS[$key];
        $duration = random_int($def['duration'][0], $def['duration'][1]);
        $asset->setPhase($key, $duration);

        $template = $def['headlines'][random_int(0, count($def['headlines']) - 1)];
        return [
            'assetId'   => $asset->getId(),
            'assetName' => $asset->getName(),
            'phase'     => $key,
            'headline'  => sprintf($template, $asset->getName()),
        ];
    }

    private function getPhaseSlopeMod(Asset $asset): float
    {
        return match ($asset->getPhase()) {
            'bull_run'   => 8.0,
            'bear_crash' => -12.0,
            'pump_dump'  => $this->pumpDumpSlopeMod($asset),
            default      => 1.0,
        };
    }

    private function pumpDumpSlopeMod(Asset $asset): float
    {
        $total = $asset->getPhaseTotalDuration();
        if ($total <= 0) {
            return 1.0;
        }
        // First half: pump (+6×), second half: dump (−10×)
        return $asset->getPhaseTicksRemaining() > $total / 2 ? 6.0 : -10.0;
    }

    private function getPhaseNoiseMod(Asset $asset): float
    {
        return (float) (self::EVENTS[$asset->getPhase()]['noiseMod'] ?? 1.0);
    }
}
