<?php

$app = require __DIR__ . '/../bootstrap.php';

echo 'redis client: ' . ($app['redis'] === null ? 'null' : 'ok') . PHP_EOL;

if ($app['redis'] !== null) {
    echo 'ping: ' . ($app['redis']->ping() ? 'true' : 'false') . PHP_EOL;
}
