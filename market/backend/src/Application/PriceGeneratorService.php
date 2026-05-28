<?php
declare(strict_types=1);
namespace Market\Application;

use Market\Domain\Entity\Asset;
use Market\Domain\Entity\PricePoint;

final class PriceGeneratorService
{
    private const GLOBAL_NEWS_CAP = 5;

    private static int   $globalMinuteBucket  = -1;
    private static int   $globalEventsCount   = 0;

    private static array $minuteBucketByAsset  = [];
    private static array $eventsCountByAsset   = [];
    private static array $eventsAllowedByAsset = [];

    private const PHASES = ['bull_run', 'bear_crash', 'pump_dump'];

    private const PHASE_DEFS = [
        'bull_run' => [
            'noiseMod' => 2.5,
            'duration' => [10, 20],
        ],
        'bear_crash' => [
            'noiseMod' => 3.0,
            'duration' => [10, 18],
        ],
        'pump_dump' => [
            'noiseMod' => 2.0,
            'duration' => [18, 28],
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
        'asset-4' => [
            // bullish
            'DogeStar Coin trending on TikTok — search volume up 4,000% overnight',
            'Anonymous wallet moves 2B DogeStar to cold storage — "diamond hands confirmed"',
            'DogeStar Coin accepted by a vending machine in Dubai — institutional adoption imminent',
            'Elon posts single 🐕 emoji — DogeStar Coin halted for volatility three times',
            'DogeStar Coin whitepaper updated; new section titled "Why This Time Is Different"',
            'DogeStar Coin breaks all-time high of $0.12 — Reddit declares "generational wealth"',
            // bearish
            'DogeStar Coin dev wallet sells 40% of supply — team calls it "liquidity provision"',
            'DogeStar Coin bridge hacked; attacker tweets "gm" afterward',
            'DogeStar Coin rug-pull suspected — website replaced with JPEG of a dog shrugging',
            'DogeStar Coin auditor report: "contract has 17 backdoors, none intentional allegedly"',
            'DogeStar Coin loses 60% in 4 hours — influencer says "great time to average down"',
            'DogeStar Coin founder arrested; tweets "I am not the founder" from jail',
        ],
        'asset-5' => [
            // bullish
            'TulipBulb Ventures AG releases new Semper Augustus cultivar — analysts raise target',
            'Dutch nobility reportedly paying 10 oxen per TulipBulb Ventures AG share',
            'TulipBulb Ventures AG Q1: bulb futures up 900% — CFO says "the soil is the moat"',
            'TulipBulb Ventures AG announces cross-listing on Amsterdam, Haarlem and Leiden exchanges',
            'TulipBulb Ventures AG introduces futures contracts — notional value exceeds Dutch GDP',
            'TulipBulb Ventures AG bulb described as "rarest in Christendom" — bidding war begins',
            // bearish
            'TulipBulb Ventures AG contract auction — no buyers appear; auctioneer checks calendar',
            'TulipBulb Ventures AG investor memo: "perhaps the intrinsic value was the friends we made"',
            'TulipBulb Ventures AG bulb delivered to buyer — recipient had expected something more',
            'Dutch States-General classifies TulipBulb Ventures AG contracts as "gambling debts"',
            'TulipBulb Ventures AG share price now below cost of actual tulip bulb',
            'TulipBulb Ventures AG annual report features only a pressed flower and no financials',
        ],
        'asset-6' => [
            // bullish
            'Pets.com Inc. secures $82M Series B — pitch deck contains the word "synergy" 41 times',
            'Pets.com Inc. Super Bowl ad airing costs more than annual revenue — "brand awareness"',
            'Pets.com Inc. sock puppet mascot voted most recognisable brand of 2000 — stock pops',
            'Pets.com Inc. acquires rival Petstore.net for $300M in stock — consolidation play',
            'Amazon rumoured to acquire Pets.com Inc. — spokesperson says "we sell books"',
            'Pets.com Inc. announces same-day delivery of 40-lb dog food bags — market loves it',
            // bearish
            'Pets.com Inc. burn rate exceeds revenue by 9:1 — CFO calls it "investment phase"',
            'Pets.com Inc. IPO lockup expires — insiders sell entire positions within 4 minutes',
            'Pets.com Inc. lays off 255 of 320 employees; sock puppet retained on retainer',
            'Pets.com Inc. Q3: shipped $1 of product at $6.15 cost — "scale will fix this"',
            'Pets.com Inc. domain purchased for $82M; current offer: $14',
            'Pets.com Inc. files Chapter 7; sock puppet auctioned for $20 at liquidation sale',
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

        $this->syncGlobalMinuteBucket();
        if (self::$globalEventsCount >= self::GLOBAL_NEWS_CAP) {
            return null;
        }

        $assetId = $asset->getId();
        $this->syncMinuteBucketForAsset($assetId);

        if ((self::$eventsCountByAsset[$assetId] ?? 0) >= (self::$eventsAllowedByAsset[$assetId] ?? 0)) {
            return null;
        }

        if (random_int(0, 999) >= $this->eventRollThreshold($assetId)) {
            return null;
        }

        self::$globalEventsCount++;
        self::$eventsCountByAsset[$assetId] = (self::$eventsCountByAsset[$assetId] ?? 0) + 1;

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

    private function syncGlobalMinuteBucket(): void
    {
        $minute = intdiv(time(), 60);
        if (self::$globalMinuteBucket === $minute) {
            return;
        }
        self::$globalMinuteBucket = $minute;
        self::$globalEventsCount  = 0;
    }

    private function syncMinuteBucketForAsset(string $assetId): void
    {
        $minute = intdiv(time(), 60);

        if ((self::$minuteBucketByAsset[$assetId] ?? -1) === $minute) {
            return;
        }

        self::$minuteBucketByAsset[$assetId]  = $minute;
        self::$eventsCountByAsset[$assetId]   = 0;
        self::$eventsAllowedByAsset[$assetId] = random_int(1, 2);
    }

    /** Higher return value = more likely to fire. Roll is random_int(0,999) >= threshold → skip. */
    private function eventRollThreshold(string $assetId): int
    {
        $eventsThisMinute   = self::$eventsCountByAsset[$assetId]   ?? 0;
        $eventsAllowed      = self::$eventsAllowedByAsset[$assetId] ?? 1;
        $remaining          = $eventsAllowed - $eventsThisMinute;

        if ($remaining <= 0) {
            return 1000;
        }

        $secondsLeft = 60 - (time() % 60);

        if ($eventsThisMinute === 0 && $secondsLeft <= 8) {
            return 1000;
        }

        if ($remaining >= 2) {
            return 15;
        }

        return 40;
    }

    private function getPhaseSlopeMod(Asset $asset): float
    {
        $phase = $asset->getPhase();
        if ($phase === 'normal') {
            return 1.0;
        }

        $total    = $asset->getPhaseTotalDuration();
        $remaining = $asset->getPhaseTicksRemaining();
        $elapsed  = max(0, $total - $remaining);
        // k=0.15: half-life ≈ 4.6 ticks → sharp initial spike then quick decay
        $decay    = $total > 0 ? exp(-0.15 * $elapsed) : 1.0;

        return match ($phase) {
            'bull_run'   =>  18.0 * $decay,
            'bear_crash' => -26.0 * $decay,
            'pump_dump'  => $this->pumpDumpSlopeMod($asset) * $decay,
            default      => 1.0,
        };
    }

    private function pumpDumpSlopeMod(Asset $asset): float
    {
        $total = $asset->getPhaseTotalDuration();
        if ($total <= 0) {
            return 1.0;
        }
        return $asset->getPhaseTicksRemaining() > $total / 2 ? 8.0 : -14.0;
    }

    private function getPhaseNoiseMod(Asset $asset): float
    {
        return (float) (self::PHASE_DEFS[$asset->getPhase()]['noiseMod'] ?? 1.0);
    }
}
