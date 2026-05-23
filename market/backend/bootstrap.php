<?php

declare(strict_types=1);

loadEnv(__DIR__ . '/.env');

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

$assetRepository = new Market\Infrastructure\Storage\InMemory\AssetRepository($config['seed_assets'] ?? []);
$priceHistoryRepository = new Market\Infrastructure\Storage\InMemory\PriceHistoryRepository();
$sessionRepository = new Market\Infrastructure\Storage\InMemory\SessionRepository();
$portfolioRepository = new Market\Infrastructure\Storage\InMemory\PortfolioRepository();
$leaderboardRepository = new Market\Infrastructure\Storage\InMemory\LeaderboardRepository();

$priceGeneratorService = new Market\Application\PriceGeneratorService();
$assetService = new Market\Application\AssetService(
    $assetRepository,
    $priceHistoryRepository,
    $priceGeneratorService,
    (int) $config['price_update_interval_seconds'],
    (int) $config['history_limit'],
    (bool) ($config['app_debug'] ?? false)
);
$sessionService = new Market\Application\SessionService(
    $sessionRepository,
    $portfolioRepository,
    (float) $config['initial_cash']
);
$portfolioService = new Market\Application\PortfolioService(
    $portfolioRepository,
    $sessionRepository,
    $assetService
);
$tradeService = new Market\Application\TradeService(
    $sessionRepository,
    $portfolioRepository,
    $assetService,
    $portfolioService
);
$leaderboardService = new Market\Application\LeaderboardService(
    $leaderboardRepository,
    $sessionRepository,
    $portfolioService
);

$controllers = [
    'asset' => new Market\Controller\AssetController($assetService),
    'portfolio' => new Market\Controller\PortfolioController($portfolioService),
    'trade' => new Market\Controller\TradeController($tradeService),
    'session' => new Market\Controller\SessionController($sessionService, $leaderboardService),
    'leaderboard' => new Market\Controller\LeaderboardController($leaderboardService),
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
];

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
