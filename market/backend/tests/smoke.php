<?php

declare(strict_types=1);

$app = require __DIR__ . '/../bootstrap.php';

$request = new Market\Http\Request('POST', '/session/start', [], ['nickname' => 'SmokeTest'], [], '');
$response = $app['router']->dispatch($request);

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "Smoke test failed: session start returned non-200\n");
    exit(1);
}

$payload = json_decode($response->getBody(), true);

if (!is_array($payload) || !($payload['ok'] ?? false) || !isset($payload['session']['sessionId'])) {
    fwrite(STDERR, "Smoke test failed: unexpected payload\n");
    exit(1);
}

echo "Smoke test OK\n";
