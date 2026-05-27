<?php

declare(strict_types=1);

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            if ($key === '') {
                continue;
            }

            $firstChar = $value[0] ?? '';
            $lastChar = $value[strlen($value) - 1] ?? '';

            if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
                $value = substr($value, 1, -1);

                if ($firstChar === '"') {
                    $value = str_replace(
                        ['\\n', '\\r', '\\t', '\\"', '\\\\'],
                        ["\n", "\r", "\t", '"', '\\'],
                        $value
                    );
                }
            }

            if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv($key . '=' . $value);
            }
        }
    }
}

loadEnv(__DIR__ . '/../.env');
loadEnv(__DIR__ . '/.env');

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Market\\';
    $baseDir = __DIR__ . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$config = require __DIR__ . '/config/app.php';

$redisUrl = getenv('REDIS_URL') ?: ($_ENV['REDIS_URL'] ?? '');
$redisPrefix = getenv('REDIS_PREFIX') ?: ($_ENV['REDIS_PREFIX'] ?? 'market:');

$redisClient = null;

if ($redisUrl !== '') {
    try {
        if (!class_exists(\Predis\Client::class)) {
            throw new RuntimeException('Predis not installed');
        }

        $redisClient = new Market\Infrastructure\Storage\Redis\RedisClient($redisUrl, $redisPrefix);
        if (!$redisClient->ping()) {
            throw new RuntimeException('Nelze se spojit s Redisem');
        }
    } catch (Throwable $e) {
        error_log("REDIS CHYBA: " . $e->getMessage());
        $redisClient = null;
    }
}

$assetRepository = new Market\Infrastructure\Storage\InMemory\AssetRepository($config['seed_assets'] ?? []);
$priceHistoryRepository = new Market\Infrastructure\Storage\InMemory\PriceHistoryRepository();
$sessionRepository = new Market\Infrastructure\Storage\InMemory\SessionRepository();
$portfolioRepository = new Market\Infrastructure\Storage\InMemory\PortfolioRepository();

if ($redisClient !== null) {
    $assetRepository = new Market\Infrastructure\Storage\Redis\RedisAssetRepository($redisClient, $config['seed_assets'] ?? []);
    $priceHistoryRepository = new Market\Infrastructure\Storage\Redis\RedisPriceHistoryRepository($redisClient);
    $sessionRepository = new Market\Infrastructure\Storage\Redis\RedisSessionRepository($redisClient);
    $portfolioRepository = new Market\Infrastructure\Storage\Redis\RedisPortfolioRepository($redisClient);
    $leaderboardRepository = new Market\Infrastructure\Storage\Redis\RedisLeaderboardRepository($redisClient);
    $orderRepository = new Market\Infrastructure\Storage\Redis\RedisOrderRepository($redisClient);
    $tradeHistoryRepository = new Market\Infrastructure\Storage\Redis\RedisTradeHistoryRepository($redisClient);
} else {
    $leaderboardRepository = new Market\Infrastructure\Storage\InMemory\LeaderboardRepository();
    $orderRepository = new Market\Infrastructure\Storage\InMemory\InMemoryOrderRepository();
    $tradeHistoryRepository = new Market\Infrastructure\Storage\InMemory\TradeHistoryRepository();
}

$eventPublisher = new Market\Application\EventPublisher($redisClient);
$activeSessionRegistry = new Market\Application\ActiveSessionRegistry($redisClient);
$priceGeneratorService = new Market\Application\PriceGeneratorService();
$sessionService = new Market\Application\SessionService(
    $sessionRepository,
    $portfolioRepository,
    (float) ($config['initial_cash'] ?? 10000.0)
);

$assetService = new Market\Application\AssetService(
    $assetRepository,
    $priceHistoryRepository,
    $priceGeneratorService,
    (int) $config['price_update_interval_seconds'],
    (int) $config['history_limit'],
    (bool) ($config['app_debug'] ?? false),
    $eventPublisher
);
$portfolioService = new Market\Application\PortfolioService(
    $portfolioRepository,
    $sessionRepository,
    $assetService,
    (float) ($config['initial_cash'] ?? 10000.0)
);
$tradeHistoryService = new Market\Application\TradeHistoryService(
    $tradeHistoryRepository,
    $sessionRepository
);
$leaderboardService = new Market\Application\LeaderboardService(
    $leaderboardRepository,
    $sessionRepository,
    $portfolioService,
    $eventPublisher,
    (int) ($config['leaderboard_limit'] ?? 10)
);
$assetService = new Market\Application\AssetService(
    $assetRepository,
    $priceHistoryRepository,
    $priceGeneratorService,
    (int) $config['price_update_interval_seconds'],
    (int) $config['history_limit'],
    (bool) ($config['app_debug'] ?? false),
    $eventPublisher,
    $leaderboardService,
    $activeSessionRegistry
);
$portfolioService = new Market\Application\PortfolioService(
    $portfolioRepository,
    $sessionRepository,
    $assetService,
    (float) ($config['initial_cash'] ?? 10000.0)
);
$marketTradeService = new Market\Application\MarketTradeService(
    $sessionRepository,
    $portfolioRepository,
    $portfolioService,
    $tradeHistoryService
);
$matchingEngine = new Market\Application\MatchingEngine(
    $orderRepository,
    $portfolioRepository,
    $eventPublisher,
    $tradeHistoryService
);
$orderService = new Market\Application\OrderService(
    $sessionRepository,
    $portfolioRepository,
    $assetService,
    $portfolioService,
    $marketTradeService,
    $orderRepository,
    $matchingEngine,
    $leaderboardService,
    $eventPublisher
);
$orderCancellationService = new Market\Application\OrderCancellationService(
    $orderRepository,
    $portfolioRepository,
    $eventPublisher,
    $orderService
);

$controllers = [
    'asset' => new Market\Controller\AssetController($assetService),
    'portfolio' => new Market\Controller\PortfolioController($portfolioService),
    'order' => new Market\Controller\OrderController($orderService),
    'session' => new Market\Controller\SessionController(
        $sessionService,
        $leaderboardService,
        $activeSessionRegistry,
        $orderCancellationService
    ),
    'leaderboard' => new Market\Controller\LeaderboardController($leaderboardService),
    'transaction' => new Market\Controller\TransactionController($tradeHistoryService),
];

$router = new Market\Http\Router((bool) ($config['app_debug'] ?? false));

$routes = require __DIR__ . '/config/routes.php';

foreach ($routes as $route) {
    $router->add(
        $route['method'],
        $route['path'],
        [$controllers[$route['controller']], $route['action']]
    );
}

return [
    'router' => $router,
    'config' => $config,
    'redis' => $redisClient,
    'activeSessionRegistry' => $activeSessionRegistry,
];
