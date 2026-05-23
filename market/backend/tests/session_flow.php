<?php

declare(strict_types=1);

$app = require __DIR__ . '/../bootstrap.php';

if ($app['redis'] === null) {
    fwrite(STDERR, "Redis not connected\n");
    exit(1);
}

$redis = $app['redis'];
$sessionRepo = new Market\Infrastructure\Storage\Redis\RedisSessionRepository($redis);
$portfolioRepo = new Market\Infrastructure\Storage\Redis\RedisPortfolioRepository($redis);
$service = new Market\Application\SessionService($sessionRepo, $portfolioRepo, 10000.0);

$started = $service->startSession('Alice');
echo 'started: ' . json_encode($started) . PHP_EOL;

$sessionId = $started['sessionId'];
$found = $sessionRepo->find($sessionId);
echo 'found: ' . ($found === null ? 'null' : $found->getNickname()) . PHP_EOL;
