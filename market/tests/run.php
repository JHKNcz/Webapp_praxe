<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Market\\';
    $baseDir = __DIR__ . '/../backend/src/';

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
<?php

declare(strict_types=1);

require __DIR__ . '/../backend/tests/run.php';
    $portfolio = new Portfolio(1000.0);
