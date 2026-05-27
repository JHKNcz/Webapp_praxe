<?php
declare(strict_types=1);
namespace Market\Application;

use Market\Domain\Entity\Asset;
use Market\Domain\Entity\PricePoint;

final class PriceGeneratorService
{
    private static int $minuteBucket = 0;
    private static int $eventsThisMinute = 0;
    private static int $eventsAllowedThisMinute = 0;

    private const MAX_NEWS_PER_MINUTE = 4;

    private const PHASES = ['bull_run', 'bear_crash', 'pump_dump'];

    private const PHASE_DEFS = [
        'bull_run' => [
            'noiseMod' => 2.0,
            'duration' => [20, 35],
        ],
        'bear_crash' => [
            'noiseMod' => 2.5,
            'duration' => [15, 25],
        ],
        'pump_dump' => [
            'noiseMod' => 1.5,
            'duration' => [30, 45],
        ],
    ];

    /** @var array<string, list<string>> 12+ meme-flavoured headlines per asset. */
    private const HEADLINES_BY_ASSET = [
        'asset-1' => [
            // bullish vibes
            'MoonRocket AI Ltd announces partnership with Ndivia — CEO spells GPU wrong in press release',
            'Jensen Huang spotted wearing MoonRocket AI Ltd hoodie at GTC — stock halted for volatility',
            'MoonRocket AI Ltd raises $3B to "accelerate AGI" — nobody asks follow-up questions',
            'MoonRocket AI Ltd blog post uses phrase "paradigm shift" 19 times; retail buys',
            'MoonRocket AI Ltd Q3 call: revenue flat, vibes immaculate, shares +18% after-hours',
            'MoonRocket AI Ltd rebrands product as "agentic" — analyst raises target immediately',
            'Sam Altman photographed at MoonRocket AI Ltd HQ — rumours start; neither confirms',
            'MoonRocket AI Ltd announces AI-powered AI to assist its existing AI — market loves it',
            // bearish vibes
            'MoonRocket AI Ltd demo revealed to be two interns and an open ChatGPT tab',
            'Short seller report: MoonRocket AI Ltd "revenue" is mostly credits they gifted themselves',
            'MoonRocket AI Ltd GPU cluster reportedly running Stable Diffusion of the company logo',
            'Hensen Juang statement: "I have never heard of MoonRocket AI Ltd" — shares crater',
            'MoonRocket AI Ltd announces layoffs while posting 47 "We\'re hiring!" LinkedIn jobs',
            'MoonRocket AI Ltd COO exits; memo leaks, contains the phrase "unsustainable burn"',
        ],
        'asset-2' => [
            // squeeze / ape energy
            'r/wallstreetbets post titles GameStart Corp "the stonk of our generation" — volume +900%',
            'GameStart Corp CEO buys $50M of own stock — posts single 💎🙌 on X',
            'Roaring Kitty changes Twitter profile picture — GameStart Corp halted three times in one hour',
            'GameStart Corp short interest hits 142% of float — Discord servers mobilising',
            'GameStart Corp announces pivot to cloud gaming — apes say "doesn\'t matter, we hold"',
            'GameStart Corp earnings: misses on revenue, beats on memes, chart goes parabolic',
            'Citron Research publishes bearish note on GameStart Corp — ratio: 91,000 to 1',
            'GameStart Corp CFO resigns — CEO replies with single 🚀 emoji and nothing else',
            'RobinHood restricts GameStart Corp buy button — SEC receives 8,000 emails in 40 minutes',
            'GameStart Corp halted 14 times in single session — exchange calls it "orderly"',
            // fundamentals-ish
            'GameStart Corp board authorises buyback — "symbolic but we\'ll take it," says Reddit',
            'GameStart Corp announces NFT marketplace; stock pumps before anyone reads terms',
            'GameStart Corp same-store sales beat very low bar — "technically not bad" says analyst',
            'GameStart Corp new loyalty programme: "PowerUp Pro Ultra" — membership fee raised 40%',
        ],
        'asset-3' => [
            // 2008 / Lehman jokes
            'Lehmann & Bros Inc announces "Repo 105 2.0" — CFO clarifies it is "completely different"',
            'Lehmann & Bros Inc stress test results: "resilient under all scenarios except real ones"',
            'Lehmann & Bros Inc marks subprime book to "vibes" pending auditor review',
            'Hank Paulson reportedly told Lehmann & Bros Inc "not our problem Sunday morning"',
            'Lehmann & Bros Inc CEO assures investors balance sheet is "Lehmann-proof" on CNBC',
            'Lehmann & Bros Inc risk model excludes 2008 data as "non-representative outlier"',
            'Lehmann & Bros Inc holds emergency Sunday call — calendar invite titled "Routine Sync"',
            'Lehmann & Bros Inc liquidity described as "adequate"; CFO defines adequate as "non-zero"',
            // dark humour
            'Lehmann & Bros Inc structured product rated AAA by three agencies, junk by one analyst',
            'Lehmann & Bros Inc bonus pool maintained despite write-downs — "retention is critical"',
            'Lehmann & Bros Inc acquires more mortgage-backed securities — "diversified exposure"',
            'Lehmann & Bros Inc CEO spotted at Davos saying "worst is behind us" — fourth year running',
            'Lehmann & Bros Inc regulator requests additional disclosure; filing says "see prior filing"',
            'Lehmann & Bros Inc auditor footnote: "going concern language added as precaution"',
        ],
    ];

    /** @return array{pricePoint: PricePoint, event: ?array} */
    public function nextPrice(Asset $asset): array
    {
        $this->advancePhase($asset);
        $event = $this->maybeFireEvent($asset);

        $currentPrice = $asset->getLastPrice();

        $slopeDrift  = $asset->getRisk() * 0.008;
        $randFactor  = random_int(-10000, 10000) / 10000;
        $newSlope    = $asset->getCurrentSlope() * 0.97
                     + $asset->getTrendSlope()   * 0.03
                     + $slopeDrift * $randFactor;
        $asset->setCurrentSlope($newSlope);

        $slopeMod  = $this->getPhaseSlopeMod($asset);
        $fairPrice = $asset->getFairPrice() + $newSlope * $slopeMod;
        $asset->setFairPrice($fairPrice);

        $reversion = ($fairPrice - $currentPrice) * 0.15;

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
        if ($asset->getPhase() !== 'normal') {
            return null;
        }

        $this->syncMinuteBucket();

        if (self::$eventsThisMinute >= self::$eventsAllowedThisMinute) {
            return null;
        }

        if (random_int(0, 999) >= $this->eventRollThreshold()) {
            return null;
        }

        self::$eventsThisMinute++;

        $phase    = self::PHASES[random_int(0, count(self::PHASES) - 1)];
        $def      = self::PHASE_DEFS[$phase];
        $duration = random_int($def['duration'][0], $def['duration'][1]);
        $asset->setPhase($phase, $duration);

        return [
            'assetId'   => $asset->getId(),
            'assetName' => $asset->getName(),
            'phase'     => $phase,
            'headline'  => $this->pickHeadline($asset),
            'ts'        => time(),
        ];
    }

    private function pickHeadline(Asset $asset): string
    {
        $pool = self::HEADLINES_BY_ASSET[$asset->getId()] ?? null;

        if ($pool === null || $pool === []) {
            return sprintf('%s is in the news — markets react', $asset->getName());
        }

        return $pool[random_int(0, count($pool) - 1)];
    }

    private function syncMinuteBucket(): void
    {
        $minute = intdiv(time(), 60);

        if ($minute === self::$minuteBucket) {
            return;
        }

        self::$minuteBucket = $minute;
        self::$eventsThisMinute = 0;
        self::$eventsAllowedThisMinute = random_int(1, self::MAX_NEWS_PER_MINUTE);
    }

    /** Higher return value = more likely to fire. Roll is random_int(0,999) >= threshold → skip. */
    private function eventRollThreshold(): int
    {
        $remaining = self::$eventsAllowedThisMinute - self::$eventsThisMinute;

        if ($remaining <= 0) {
            return 1000;
        }

        $secondsLeft = 60 - (time() % 60);

        // If no event fired yet and minute is almost over, guarantee one.
        if (self::$eventsThisMinute === 0 && $secondsLeft <= 8) {
            return 1000;
        }

        // Base probability scales with remaining budget vs seconds left.
        // ~1% base, ramps up as quota unfilled near end.
        if ($remaining >= 3) {
            return 12;
        }

        return 30 + (3 - $remaining) * 25;
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

        return $asset->getPhaseTicksRemaining() > $total / 2 ? 6.0 : -10.0;
    }

    private function getPhaseNoiseMod(Asset $asset): float
    {
        return (float) (self::PHASE_DEFS[$asset->getPhase()]['noiseMod'] ?? 1.0);
    }
}
