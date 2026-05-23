<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Market\\';
    $baseDir = __DIR__ . '/../src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

use Market\Application\AssetService;
use Market\Application\PortfolioService;
use Market\Application\PriceGeneratorService;
use Market\Application\SessionService;
use Market\Application\TradeService;
use Market\Domain\Entity\Asset;
use Market\Domain\Entity\Portfolio;
use Market\Infrastructure\Storage\InMemory\AssetRepository;
use Market\Infrastructure\Storage\InMemory\PortfolioRepository;
use Market\Infrastructure\Storage\InMemory\PriceHistoryRepository;
use Market\Infrastructure\Storage\InMemory\SessionRepository;

$tests = [];

$tests['Portfolio buy/sell flow'] = static function (): void {
    $portfolio = new Portfolio(1000.0);
    $portfolio->buy('asset-1', 2, 100.0);

    assertEquals(800.0, $portfolio->getCash(), 'Cash after buy');
    assertEquals(2, $portfolio->getHolding('asset-1')?->getQuantity(), 'Quantity after buy');

    $portfolio->sell('asset-1', 1, 110.0);

    assertEquals(910.0, $portfolio->getCash(), 'Cash after sell');
    assertEquals(1, $portfolio->getHolding('asset-1')?->getQuantity(), 'Quantity after sell');
};

$tests['Portfolio rejects insufficient cash'] = static function (): void {
    $portfolio = new Portfolio(50.0);

    assertThrows(static function () use ($portfolio): void {
        $portfolio->buy('asset-1', 1, 100.0);
    }, 'Not enough cash');
};

$tests['Price generator mean reversion'] = static function (): void {
    $generator = new PriceGeneratorService();
    $asset = new Asset('asset-1', 'Alpha', 80.0, 100.0, 0.0, 0.0);

    $point = $generator->nextPrice($asset);
    $price = $point->getPrice();

    assertTrue($price > 80.0, 'Price moves toward fair price');
    assertTrue($price < 100.0, 'Price does not jump past fair price');
    assertTrue($price >= 1.0, 'Price stays >= 1.0');
};

$tests['Asset service builds history and refreshes'] = static function (): void {
    $assetRepo = new AssetRepository([
        ['id' => 'asset-1', 'name' => 'Alpha', 'lastPrice' => 100.0],
    ]);
    $historyRepo = new PriceHistoryRepository();
    $generator = new PriceGeneratorService();

    $service = new AssetService($assetRepo, $historyRepo, $generator, 0, 10, false);

    $first = $service->getHistory('asset-1');
    assertTrue(count($first) >= 1, 'History contains initial point');

    $second = $service->getHistory('asset-1');
    assertTrue(count($second) >= count($first), 'History grows after refresh');
};

$tests['Session + trade services'] = static function (): void {
    $assetRepo = new AssetRepository([
        ['id' => 'asset-1', 'name' => 'Alpha', 'lastPrice' => 100.0],
    ]);
    $historyRepo = new PriceHistoryRepository();
    $sessionRepo = new SessionRepository();
    $portfolioRepo = new PortfolioRepository();
    $generator = new PriceGeneratorService();

    $assetService = new AssetService($assetRepo, $historyRepo, $generator, 0, 10, false);
    $portfolioService = new PortfolioService($portfolioRepo, $sessionRepo, $assetService);
    $sessionService = new SessionService($sessionRepo, $portfolioRepo, 1000.0);
    $tradeService = new TradeService($sessionRepo, $portfolioRepo, $assetService, $portfolioService);

    $session = $sessionService->startSession();
    $sessionId = (string) $session['sessionId'];

    $trade = $tradeService->buy($sessionId, 'asset-1', 2);
    assertEquals('buy', $trade['type'], 'Trade type buy');

    $trade = $tradeService->sell($sessionId, 'asset-1', 1);
    assertEquals('sell', $trade['type'], 'Trade type sell');
};

$tests['End session clears data'] = static function (): void {
    $assetRepo = new AssetRepository([
        ['id' => 'asset-1', 'name' => 'Alpha', 'lastPrice' => 100.0],
    ]);
    $historyRepo = new PriceHistoryRepository();
    $sessionRepo = new SessionRepository();
    $portfolioRepo = new PortfolioRepository();
    $generator = new PriceGeneratorService();

    $assetService = new AssetService($assetRepo, $historyRepo, $generator, 0, 10, false);
    $portfolioService = new PortfolioService($portfolioRepo, $sessionRepo, $assetService);
    $sessionService = new SessionService($sessionRepo, $portfolioRepo, 1000.0);

    $session = $sessionService->startSession();
    $sessionId = (string) $session['sessionId'];

    $sessionService->endSession($sessionId);

    assertTrue($sessionRepo->find($sessionId) === null, 'Session deleted');
    assertTrue($portfolioRepo->find($sessionId) === null, 'Portfolio deleted');
};

$tests['Router assets endpoints'] = static function (): void {
    $app = require __DIR__ . '/../bootstrap.php';

    $request = new Market\Http\Request('GET', '/assets', [], [], [], '');
    $response = $app['router']->dispatch($request);
    $payload = json_decode($response->getBody(), true);

    assertTrue(($payload['ok'] ?? false) === true, 'Assets endpoint ok');
    assertTrue(is_array($payload['items'] ?? null), 'Assets endpoint returns items');

    $request = new Market\Http\Request('GET', '/assets/tick', [], [], [], '');
    $response = $app['router']->dispatch($request);
    $payload = json_decode($response->getBody(), true);

    assertTrue(($payload['ok'] ?? false) === true, 'Assets tick endpoint ok');
    assertTrue(is_array($payload['items'] ?? null), 'Assets tick returns items');
};

$passed = 0;
$failed = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
        echo "[PASS] {$name}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "[FAIL] {$name} - {$exception->getMessage()}\n";
    }
}

echo "\nDone. Passed: {$passed}, Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertEquals(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        $expectedText = var_export($expected, true);
        $actualText = var_export($actual, true);
        throw new RuntimeException("{$message} (expected {$expectedText}, got {$actualText})");
    }
}

function assertThrows(callable $callback, string $expectedMessage): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if ($expectedMessage !== '' && !str_contains($exception->getMessage(), $expectedMessage)) {
            throw new RuntimeException("Unexpected exception message: {$exception->getMessage()}");
        }
        return;
    }

    throw new RuntimeException('Expected exception was not thrown');
}
