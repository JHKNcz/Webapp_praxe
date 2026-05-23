<?php

declare(strict_types=1);

$app = require __DIR__ . '/../bootstrap.php';

if ($app['redis'] === null) {
    fwrite(STDERR, "Redis not connected — skip price persistence check\n");
    exit(0);
}

$router = $app['router'];

$tick = static function () use ($router): array {
    $request = new Market\Http\Request('GET', '/assets/tick', [], [], [], '');
    $response = $router->dispatch($request);
    $payload = json_decode($response->getBody(), true);

    return is_array($payload['items'] ?? null) ? $payload['items'] : [];
};

$first = $tick();
$second = $tick();

if ($first === [] || $second === []) {
    fwrite(STDERR, "Price tick returned empty items\n");
    exit(1);
}

$firstPrice = (float) ($first[0]['lastPrice'] ?? 0);
$secondPrice = (float) ($second[0]['lastPrice'] ?? 0);

echo "asset-1: {$firstPrice} -> {$secondPrice}\n";

if ($firstPrice === $secondPrice) {
    fwrite(STDERR, "Price did not change between forced ticks\n");
    exit(1);
}

echo "Redis price persistence OK\n";
