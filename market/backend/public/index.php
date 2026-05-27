<?php

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
	header('Access-Control-Allow-Headers: Content-Type, Authorization');
	header('Access-Control-Max-Age: 86400');
	http_response_code(204);
	exit;
}

$app = require __DIR__ . '/../bootstrap.php';

$request = Market\Http\Request::fromGlobals();
$response = $app['router']->dispatch($request);
$response->header('Access-Control-Allow-Origin', '*');
$response->header('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS');
$response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
$response->send();
