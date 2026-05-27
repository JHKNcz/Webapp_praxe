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

use Market\Application\ActiveSessionRegistry;
use Market\Application\AssetService;
use Market\Application\EventPublisher;
use Market\Application\LeaderboardService;
use Market\Application\MarketTradeService;
use Market\Application\MatchingEngine;
use Market\Application\OrderCancellationService;
use Market\Application\OrderService;
use Market\Application\PortfolioService;
use Market\Application\PriceGeneratorService;
use Market\Application\SessionService;
use Market\Application\TradeHistoryService;
use Market\Domain\Entity\Asset;
use Market\Domain\Entity\Portfolio;
use Market\Infrastructure\Storage\InMemory\AssetRepository;
use Market\Infrastructure\Storage\InMemory\InMemoryOrderRepository;
use Market\Infrastructure\Storage\InMemory\LeaderboardRepository;
use Market\Infrastructure\Storage\InMemory\PortfolioRepository;
use Market\Infrastructure\Storage\InMemory\PriceHistoryRepository;
use Market\Infrastructure\Storage\InMemory\SessionRepository;
use Market\Infrastructure\Storage\InMemory\TradeHistoryRepository;

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

    $result = $generator->nextPrice($asset);
    $price = $result['pricePoint']->getPrice();

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

$tests['Market fill instant buy'] = static function (): void {
    [$orderService, $sessionService, $leaderboardService] = buildOrderFixture();

    $session = $sessionService->startSession('SoloTrader');
    $result = $orderService->placeOrder((string) $session['sessionId'], 'asset-1', 'buy', 2);

    assertEquals('market', $result['fillType'], 'Market fill type');
    assertTrue(count($result['trades']) === 1, 'One market trade executed');
    assertEquals(2, $result['trades'][0]['quantity'], 'Traded quantity matches order');
};

$tests['Market fill sell after buy'] = static function (): void {
    [$orderService, $sessionService] = buildOrderFixture();

    $session = $sessionService->startSession('RoundTrip');
    $sessionId = (string) $session['sessionId'];

    $orderService->placeOrder($sessionId, 'asset-1', 'buy', 3);
    $result = $orderService->placeOrder($sessionId, 'asset-1', 'sell', 1);

    assertTrue(count($result['trades']) === 1, 'Sell filled at market');
    assertEquals('sell', $result['trades'][0]['side'], 'Sell side recorded');
};

$tests['Live leaderboard updates on session start'] = static function (): void {
    [$orderService, $sessionService, $leaderboardService] = buildOrderFixture();

    $session = $sessionService->startSession('BoardPlayer');
    $leaderboardService->syncLive((string) $session['sessionId']);

    $top = $leaderboardService->top(5);
    assertTrue(count($top) >= 1, 'Live board has active player');
    assertEquals('BoardPlayer', $top[0]['displayName'], 'Nickname on live board');
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

$tests['Transaction history records market trade'] = static function (): void {
    [$orderService, $sessionService, , $tradeHistoryService] = buildOrderFixture();

    $session = $sessionService->startSession('HistTrader');
    $sessionId = (string) $session['sessionId'];

    $orderService->placeOrder($sessionId, 'asset-1', 'buy', 1, 'market');
    $items = $tradeHistoryService->list($sessionId, 10);

    assertTrue(count($items) >= 1, 'History has entry');
    assertEquals('market', $items[0]['type'], 'Market type recorded');
    assertEquals('buy', $items[0]['side'], 'Buy side recorded');
};

$tests['FIFO limit orders match between players'] = static function (): void {
    [$orderService, $sessionService] = buildOrderFixture();

    $seller = $sessionService->startSession('Seller1');
    $buyer = $sessionService->startSession('Buyer1');

    seedHolding($orderService, (string) $seller['sessionId'], 1);
    $orderService->placeOrder((string) $seller['sessionId'], 'asset-1', 'sell', 1, 'limit');
    bumpFixtureAssetPrice($orderService, 'asset-1', 100.0);
    $result = $orderService->placeOrder((string) $buyer['sessionId'], 'asset-1', 'buy', 1, 'limit');

    assertTrue(count($result['trades']) === 1, 'P2P trade executed');
    assertEquals('limit', $result['fillType'], 'Limit fill type');
};

$tests['Take order executes against resting sell'] = static function (): void {
    [$orderService, $sessionService] = buildOrderFixture();

    $seller = $sessionService->startSession('MakerSell');
    $buyer = $sessionService->startSession('TakerBuy');

    seedHolding($orderService, (string) $seller['sessionId'], 3);
    $posted = $orderService->placeOrder((string) $seller['sessionId'], 'asset-1', 'sell', 3, 'limit');
    $orderId = (string) ($posted['order']['id'] ?? '');

    $result = $orderService->takeOrder((string) $buyer['sessionId'], $orderId, 2);

    assertEquals('p2p', $result['fillType'], 'Take fill type');
    assertEquals(2, $result['trades'][0]['quantity'], 'Took two shares');
};

$tests['Transactions API returns history'] = static function (): void {
    $app = require __DIR__ . '/../bootstrap.php';

    $start = new Market\Http\Request('POST', '/session/start', [], ['nickname' => 'ApiHist'], [], '');
    $startResponse = $app['router']->dispatch($start);
    $startPayload = json_decode($startResponse->getBody(), true);
    $sessionId = (string) ($startPayload['session']['sessionId'] ?? '');

    $order = new Market\Http\Request(
        'POST',
        '/orders',
        [],
        [
            'sessionId' => $sessionId,
            'assetId' => 'asset-1',
            'side' => 'buy',
            'quantity' => 1,
            'mode' => 'market',
        ],
        [],
        ''
    );
    $app['router']->dispatch($order);

    $txRequest = new Market\Http\Request('GET', '/transactions', ['sessionId' => $sessionId], [], [], '');
    $txResponse = $app['router']->dispatch($txRequest);
    $txPayload = json_decode($txResponse->getBody(), true);

    assertTrue(($txPayload['ok'] ?? false) === true, 'Transactions endpoint ok');
    assertTrue(count($txPayload['items'] ?? []) >= 1, 'Transactions returned');
};

$tests['Order book lists posted orders'] = static function (): void {
    [$orderService, $sessionService] = buildOrderFixture();

    $seller = $sessionService->startSession('BookSeller');
    seedHolding($orderService, (string) $seller['sessionId'], 2);
    $orderService->placeOrder((string) $seller['sessionId'], 'asset-1', 'sell', 2, 'limit');

    $book = $orderService->getOrderBook('asset-1');
    assertTrue(count($book['asks']) >= 1, 'Ask in order book');
    assertEquals('BookSeller', $book['asks'][0]['nickname'], 'Nickname on book row');
};

$tests['Portfolio summary includes PnL'] = static function (): void {
    $assetRepo = new AssetRepository([
        ['id' => 'asset-1', 'name' => 'Alpha', 'lastPrice' => 110.0],
    ]);
    $historyRepo = new PriceHistoryRepository();
    $sessionRepo = new SessionRepository();
    $portfolioRepo = new PortfolioRepository();
    $generator = new PriceGeneratorService();
    $assetService = new AssetService($assetRepo, $historyRepo, $generator, 0, 10, false);
    $portfolioService = new PortfolioService($portfolioRepo, $sessionRepo, $assetService, 10000.0);

    $session = (new SessionService($sessionRepo, $portfolioRepo, 10000.0))->startSession('PnlTrader');
    $sessionId = (string) $session['sessionId'];
    $portfolio = $portfolioService->getPortfolio($sessionId);
    $portfolio->buy('asset-1', 10, 100.0);
    $portfolioRepo->save($sessionId, $portfolio);

    $summary = $portfolioService->getPortfolioSummary($sessionId);

    assertEquals(10000.0, $summary['initialCash'], 'Initial cash in summary');
    assertTrue($summary['pnl'] > 0, 'PnL positive when price rose');
    assertEquals(100.0, $summary['holdings'][0]['unrealizedPnl'], 'Unrealized PnL per holding');
};

$tests['Session starts with configured initial cash'] = static function (): void {
    $initialCashEnv = getenv('INITIAL_CASH');
    $initialCash = $initialCashEnv !== false && $initialCashEnv !== ''
        ? (float) $initialCashEnv
        : 10000.0;

    $sessionRepo = new SessionRepository();
    $portfolioRepo = new PortfolioRepository();
    $sessionService = new SessionService($sessionRepo, $portfolioRepo, $initialCash);

    putenv('INITIAL_CASH=5000');
    $resolved = getenv('INITIAL_CASH') !== false && getenv('INITIAL_CASH') !== ''
        ? (float) getenv('INITIAL_CASH')
        : 10000.0;
    $sessionServiceEnv = new SessionService($sessionRepo, $portfolioRepo, $resolved);
    $session = $sessionServiceEnv->startSession('CashTest');

    assertEquals(5000.0, (float) $session['cash'], 'Initial cash from env resolution');
    putenv('INITIAL_CASH');
};

$tests['End session cancels open orders and unlocks collateral'] = static function (): void {
    [$orderService, $sessionService, , , $orderRepo, $portfolioRepo] = buildOrderFixture();

    $session = $sessionService->startSession('Leaver2');
    $sessionId = (string) $session['sessionId'];

    $orderService->placeOrder($sessionId, 'asset-1', 'buy', 2, 'limit');
    assertTrue(count($orderRepo->findOpenBySession($sessionId)) === 1, 'Order posted');

    $portfolioAfterPost = $portfolioRepo->find($sessionId);
    $cashLocked = $portfolioAfterPost !== null ? $portfolioAfterPost->getCash() : -1.0;
    assertTrue($cashLocked < 10000.0, 'Cash locked for buy post');

    $cancellation = new OrderCancellationService($orderRepo, $portfolioRepo);
    $cancellation->cancelAllForSession($sessionId);

    assertTrue(count($orderRepo->findOpenBySession($sessionId)) === 0, 'No open orders');
    $portfolioAfterCancel = $portfolioRepo->find($sessionId);
    assertTrue($portfolioAfterCancel !== null, 'Portfolio still exists');
    assertEquals(10000.0, $portfolioAfterCancel->getCash(), 'Cash unlocked');

    $sessionService->endSession($sessionId);
};

$tests['Session resume re-registers active session'] = static function (): void {
    $app = require __DIR__ . '/../bootstrap.php';
    $router = $app['router'];
    $registry = $app['activeSessionRegistry'];

    $startResponse = $router->dispatch(
        new Market\Http\Request('POST', '/session/start', [], ['nickname' => 'ResumeMe'], [], '')
    );
    $startPayload = json_decode($startResponse->getBody(), true);
    $sessionId = (string) ($startPayload['session']['sessionId'] ?? '');

    $registry->remove($sessionId);
    assertTrue(!in_array($sessionId, $registry->all(), true), 'Session removed from active set');

    $resumeResponse = $router->dispatch(
        new Market\Http\Request('POST', '/session/resume', [], ['sessionId' => $sessionId], [], '')
    );
    $resumePayload = json_decode($resumeResponse->getBody(), true);

    assertTrue(($resumePayload['ok'] ?? false) === true, 'Resume ok');
    assertTrue(in_array($sessionId, $registry->all(), true), 'Session back in active set');
};

$tests['FIFO matches at resting sell price when prices cross'] = static function (): void {
    [$orderService, $sessionService] = buildOrderFixture();

    $seller = $sessionService->startSession('FifoSeller');
    $buyer = $sessionService->startSession('FifoBuyer');

    seedHolding($orderService, (string) $seller['sessionId'], 1);
    $orderService->placeOrder((string) $seller['sessionId'], 'asset-1', 'sell', 1, 'limit');
    bumpFixtureAssetPrice($orderService, 'asset-1', 105.0);

    $result = $orderService->placeOrder((string) $buyer['sessionId'], 'asset-1', 'buy', 1, 'limit');

    assertTrue(count($result['trades']) === 1, 'P2P trade executed after price tick');
    assertEquals(100.0, (float) $result['trades'][0]['price'], 'Fill at resting sell price');
};

$tests['Cancel releases locked sell shares after partial fill'] = static function (): void {
    [$orderService, $sessionService, , , $orderRepo, $portfolioRepo] = buildOrderFixture();

    $seller = $sessionService->startSession('PartialSeller');
    $taker = $sessionService->startSession('PartialTaker');
    $sellerId = (string) $seller['sessionId'];
    $takerId = (string) $taker['sessionId'];

    seedHolding($orderService, $sellerId, 10);
    $posted = $orderService->placeOrder($sellerId, 'asset-1', 'sell', 10, 'limit');
    $orderId = (string) ($posted['order']['id'] ?? '');

    $portfolioAfterPost = $portfolioRepo->find($sellerId);
    assertEquals(0, $portfolioAfterPost?->getHolding('asset-1')?->getQuantity() ?? 0, 'Shares locked on post');

    $orderService->takeOrder($takerId, $orderId, 4);

    $portfolioAfterTake = $portfolioRepo->find($sellerId);
    assertEquals(0, $portfolioAfterTake?->getHolding('asset-1')?->getQuantity() ?? 0, 'Unfilled shares stay in order');
    assertEquals(9400.0, $portfolioAfterTake?->getCash() ?? -1.0, 'Cash credited for partial fill');

    $cancellation = new OrderCancellationService($orderRepo, $portfolioRepo);
    $cancellation->cancelAllForSession($sellerId);

    $portfolioAfterCancel = $portfolioRepo->find($sellerId);
    assertEquals(6, $portfolioAfterCancel?->getHolding('asset-1')?->getQuantity() ?? -1, 'Remaining shares unlocked');
    assertEquals(9400.0, $portfolioAfterCancel?->getCash() ?? -1.0, 'Partial sale proceeds kept');
    assertTrue(count($orderRepo->findOpenBySession($sellerId)) === 0, 'Order cancelled');
};

$tests['Cancel releases locked buy cash'] = static function (): void {
    [$orderService, $sessionService, , , $orderRepo, $portfolioRepo] = buildOrderFixture();

    $session = $sessionService->startSession('BuyCancel');
    $sessionId = (string) $session['sessionId'];

    $orderService->placeOrder($sessionId, 'asset-1', 'buy', 3, 'limit');

    $portfolioAfterPost = $portfolioRepo->find($sessionId);
    assertEquals(9700.0, $portfolioAfterPost?->getCash() ?? -1.0, 'Cash locked on buy post');

    $cancellation = new OrderCancellationService($orderRepo, $portfolioRepo);
    $cancellation->cancelAllForSession($sessionId);

    $portfolioAfterCancel = $portfolioRepo->find($sessionId);
    assertEquals(10000.0, $portfolioAfterCancel?->getCash() ?? -1.0, 'Buy cash unlocked on cancel');
    assertTrue(count($orderRepo->findOpenBySession($sessionId)) === 0, 'Buy order cancelled');
};

$tests['Self-trade skip does not block cross-session match'] = static function (): void {
    [$orderService, $sessionService] = buildOrderFixture();

    $solo = $sessionService->startSession('SoloCross');
    $soloId = (string) $solo['sessionId'];
    $counterparty = $sessionService->startSession('Counterparty');
    $counterId = (string) $counterparty['sessionId'];

    seedHolding($orderService, $soloId, 10);
    seedHolding($orderService, $counterId, 5);

    $orderService->placeOrder($soloId, 'asset-1', 'buy', 5, 'limit');
    $orderService->placeOrder($soloId, 'asset-1', 'sell', 5, 'limit');

    $result = $orderService->placeOrder($counterId, 'asset-1', 'sell', 5, 'limit');

    assertTrue(count($result['trades']) === 1, 'Cross-session trade after self-trade skip');
    assertEquals($soloId, (string) ($result['trades'][0]['buySessionId'] ?? ''), 'Solo buy matched counterparty sell');
};

$tests['Partial sell fill keeps locked and sold shares consistent'] = static function (): void {
    [$orderService, $sessionService, , , , $portfolioRepo] = buildOrderFixture();

    $seller = $sessionService->startSession('SellFifo');
    $buyer = $sessionService->startSession('BuyFifo');
    $sellerId = (string) $seller['sessionId'];
    $buyerId = (string) $buyer['sessionId'];

    seedHolding($orderService, $sellerId, 10);
    $orderService->placeOrder($sellerId, 'asset-1', 'sell', 10, 'limit');

    $sellerPortfolio = $portfolioRepo->find($sellerId);
    assertEquals(0, $sellerPortfolio?->getHolding('asset-1')?->getQuantity() ?? 0, 'All shares locked in resting sell');

    $result = $orderService->placeOrder($buyerId, 'asset-1', 'buy', 4, 'limit');

    assertTrue(count($result['trades']) === 1, 'Partial queue match');
    assertEquals(4, $result['trades'][0]['quantity'], 'Four shares matched');

    $sellerAfter = $portfolioRepo->find($sellerId);
    assertEquals(0, $sellerAfter?->getHolding('asset-1')?->getQuantity() ?? 0, 'Unfilled shares remain in order not portfolio');
    assertEquals(9400.0, $sellerAfter?->getCash() ?? -1.0, 'Seller received partial proceeds');

    $postedSell = $orderService->getOpenOrders($sellerId);
    assertTrue(count($postedSell) === 1, 'Resting sell still open');
    assertEquals(6, $postedSell[0]['remainingQty'], 'Six shares still locked in order');
};

$tests['Leaderboard repository ordering'] = static function (): void {
    $repo = new LeaderboardRepository();
    $repo->upsertLive(new Market\Domain\Entity\LeaderboardEntry('s1', 'A', 100.0, 1));
    $repo->upsertLive(new Market\Domain\Entity\LeaderboardEntry('s2', 'B', 250.0, 2));
    $repo->upsertLive(new Market\Domain\Entity\LeaderboardEntry('s3', 'C', 150.0, 3));

    $top = $repo->topLive(2);
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

/** @return array{0: OrderService, 1: SessionService, 2: LeaderboardService, 3: TradeHistoryService, 4: InMemoryOrderRepository, 5: PortfolioRepository} */
function buildOrderFixture(): array
{
    $assetRepo = new AssetRepository([
        ['id' => 'asset-1', 'name' => 'Alpha', 'lastPrice' => 100.0],
    ]);
    $historyRepo = new PriceHistoryRepository();
    $sessionRepo = new SessionRepository();
    $portfolioRepo = new PortfolioRepository();
    $leaderboardRepo = new LeaderboardRepository();
    $orderRepo = new InMemoryOrderRepository();
    $tradeHistoryRepo = new TradeHistoryRepository();
    $generator = new PriceGeneratorService();
    $publisher = new EventPublisher(null);

    // Large interval keeps lastPrice stable between order posts (interval 0 ticks every getAsset call).
    $assetService = new AssetService($assetRepo, $historyRepo, $generator, 86400, 10, false, $publisher);
    $portfolioService = new PortfolioService($portfolioRepo, $sessionRepo, $assetService);
    $sessionService = new SessionService($sessionRepo, $portfolioRepo, 10000.0);
    $leaderboardService = new LeaderboardService($leaderboardRepo, $sessionRepo, $portfolioService, $publisher, 10);
    $tradeHistoryService = new TradeHistoryService($tradeHistoryRepo, $sessionRepo);
    $marketTradeService = new MarketTradeService(
        $sessionRepo,
        $portfolioRepo,
        $portfolioService,
        $tradeHistoryService
    );
    $matchingEngine = new MatchingEngine($orderRepo, $portfolioRepo, $publisher, $tradeHistoryService);
    $orderService = new OrderService(
        $sessionRepo,
        $portfolioRepo,
        $assetService,
        $portfolioService,
        $marketTradeService,
        $orderRepo,
        $matchingEngine,
        $leaderboardService,
        $publisher
    );

    return [$orderService, $sessionService, $leaderboardService, $tradeHistoryService, $orderRepo, $portfolioRepo];
}

function bumpFixtureAssetPrice(OrderService $orderService, string $assetId, float $price): void
{
    $orderReflection = new ReflectionClass($orderService);
    $assetServiceProperty = $orderReflection->getProperty('assetService');
    $assetServiceProperty->setAccessible(true);
    $assetService = $assetServiceProperty->getValue($orderService);

    $assetServiceReflection = new ReflectionClass($assetService);
    $assetRepositoryProperty = $assetServiceReflection->getProperty('assetRepository');
    $assetRepositoryProperty->setAccessible(true);
    $assetRepository = $assetRepositoryProperty->getValue($assetService);

    $asset = $assetRepository->find($assetId);
    assertTrue($asset !== null, 'Asset exists for price bump');
    $asset->setLastPrice($price);
    $assetRepository->save($asset);
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
