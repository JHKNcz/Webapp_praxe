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
use Market\Application\EventPublisher;
use Market\Application\MatchingEngine;
use Market\Application\OrderService;
use Market\Application\PortfolioService;
use Market\Application\PriceGeneratorService;
use Market\Application\SessionService;
use Market\Domain\Entity\Asset;
use Market\Domain\Entity\Portfolio;
use Market\Infrastructure\Storage\InMemory\AssetRepository;
use Market\Infrastructure\Storage\InMemory\InMemoryOrderRepository;
use Market\Infrastructure\Storage\InMemory\LeaderboardRepository;
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

$tests['Session requires nickname'] = static function (): void {
    $sessionRepo = new SessionRepository();
    $portfolioRepo = new PortfolioRepository();
    $sessionService = new SessionService($sessionRepo, $portfolioRepo, 1000.0);

    assertThrows(static function () use ($sessionService): void {
        $sessionService->startSession('');
    }, 'Nickname is required');

    $session = $sessionService->startSession('Trader42');
    assertEquals('Trader42', $session['nickname'], 'Nickname stored on session');
};

$tests['MatchingEngine FIFO matches first sell'] = static function (): void {
    [$orderService, $sessionService] = buildOrderFixture();

    $seller = $sessionService->startSession('Seller1');
    $seller2 = $sessionService->startSession('Seller2');
    $buyer = $sessionService->startSession('Buyer1');

    seedHolding($orderService, (string) $seller['sessionId'], 5);
    seedHolding($orderService, (string) $seller2['sessionId'], 5);

    $orderService->placeOrder((string) $seller['sessionId'], 'asset-1', 'sell', 5);
    $orderService->placeOrder((string) $seller2['sessionId'], 'asset-1', 'sell', 5);
    $result = $orderService->placeOrder((string) $buyer['sessionId'], 'asset-1', 'buy', 5);

    assertTrue(count($result['trades']) === 1, 'One trade executed');
    assertEquals((string) $seller['sessionId'], $result['trades'][0]['sellSessionId'], 'First sell matched FIFO');
};

$tests['MatchingEngine partial fill keeps remainder'] = static function (): void {
    [$orderService, $sessionService] = buildOrderFixture();

    $seller = $sessionService->startSession('SellerA');
    $buyer = $sessionService->startSession('BuyerA');

    seedHolding($orderService, (string) $seller['sessionId'], 5);
    $orderService->placeOrder((string) $seller['sessionId'], 'asset-1', 'sell', 5);
    $result = $orderService->placeOrder((string) $buyer['sessionId'], 'asset-1', 'buy', 10);

    assertTrue(count($result['trades']) === 1, 'Partial trade executed');
    assertEquals(5, $result['trades'][0]['quantity'], 'Traded quantity is 5');
    assertEquals(5, $result['order']['remainingQty'], 'Buy order remainder queued');
};

$tests['OrderService rejects buy without cash'] = static function (): void {
    [$orderService, $sessionService] = buildOrderFixture();

    $session = $sessionService->startSession('PoorTrader');

    assertThrows(static function () use ($orderService, $session): void {
        $orderService->placeOrder((string) $session['sessionId'], 'asset-1', 'buy', 1000);
    }, 'Not enough cash');
};

$tests['OrderService rejects sell without holdings'] = static function (): void {
    [$orderService, $sessionService] = buildOrderFixture();

    $session = $sessionService->startSession('EmptyTrader');

    assertThrows(static function () use ($orderService, $session): void {
        $orderService->placeOrder((string) $session['sessionId'], 'asset-1', 'sell', 1);
    }, 'Holding not found');
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

    $session = $sessionService->startSession('Leaver');
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

$tests['Leaderboard repository ordering'] = static function (): void {
    $repo = new LeaderboardRepository();
    $repo->add(new Market\Domain\Entity\LeaderboardEntry('s1', 'A', 100.0, 1));
    $repo->add(new Market\Domain\Entity\LeaderboardEntry('s2', 'B', 250.0, 2));
    $repo->add(new Market\Domain\Entity\LeaderboardEntry('s3', 'C', 150.0, 3));

    $top = $repo->top(2);
    assertEquals(250.0, $top[0]->getScore(), 'Highest score first');
    assertEquals(150.0, $top[1]->getScore(), 'Second score next');
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

/** @return array{0: OrderService, 1: SessionService} */
function buildOrderFixture(): array
{
    $assetRepo = new AssetRepository([
        ['id' => 'asset-1', 'name' => 'Alpha', 'lastPrice' => 100.0],
    ]);
    $historyRepo = new PriceHistoryRepository();
    $sessionRepo = new SessionRepository();
    $portfolioRepo = new PortfolioRepository();
    $orderRepo = new InMemoryOrderRepository();
    $generator = new PriceGeneratorService();
    $publisher = new EventPublisher(null);

    $assetService = new AssetService($assetRepo, $historyRepo, $generator, 0, 10, false, $publisher);
    $portfolioService = new PortfolioService($portfolioRepo, $sessionRepo, $assetService);
    $sessionService = new SessionService($sessionRepo, $portfolioRepo, 10000.0);
    $matchingEngine = new MatchingEngine($orderRepo, $portfolioRepo, $publisher);
    $orderService = new OrderService(
        $sessionRepo,
        $portfolioRepo,
        $assetService,
        $portfolioService,
        $orderRepo,
        $matchingEngine
    );

    return [$orderService, $sessionService];
}

function seedHolding(OrderService $orderService, string $sessionId, int $quantity): void
{
    $reflection = new ReflectionClass($orderService);
    $portfolioServiceProp = $reflection->getProperty('portfolioService');
    $portfolioServiceProp->setAccessible(true);
    /** @var PortfolioService $portfolioService */
    $portfolioService = $portfolioServiceProp->getValue($orderService);

    $portfolio = $portfolioService->getPortfolio($sessionId);
    $portfolio->buy('asset-1', $quantity, 100.0);

    $portfolioRepoProp = $reflection->getProperty('portfolioRepository');
    $portfolioRepoProp->setAccessible(true);
    $portfolioRepo = $portfolioRepoProp->getValue($orderService);
    $portfolioRepo->save($sessionId, $portfolio);
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
