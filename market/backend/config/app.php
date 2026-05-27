<?php

declare(strict_types=1);

$debugEnv = getenv('APP_DEBUG');
$debugValue = $debugEnv === false ? false : filter_var($debugEnv, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$appDebug = $debugValue === null ? false : $debugValue;

$initialCashEnv = getenv('INITIAL_CASH');
$initialCash = $initialCashEnv !== false && $initialCashEnv !== ''
    ? (float) $initialCashEnv
    : 1000.0;

return [
    'app_name' => 'Market',
    'app_debug' => $appDebug,
    'initial_cash' => $initialCash,
    'price_update_interval_seconds' => 1,
    'history_limit' => 200,
    'leaderboard_limit' => 10,
    'cors_allowed_origins' => ['*'],
    'seed_assets' => [
        [
            'id' => 'asset-1',
            'name' => 'MoonRocket AI Ltd',
            'lastPrice' => 5.0,
            'fairPrice' => 5.0,
            'risk' => 0.55,
            'trendSlope' => 0.005,
        ],
        [
            'id' => 'asset-2',
            'name' => 'GameStopp Corp',
            'lastPrice' => 25.0,
            'fairPrice' => 25.0,
            'risk' => 0.30,
            'trendSlope' => 0.0,
        ],
        [
            'id' => 'asset-3',
            'name' => 'Lehmann & Bros Inc',
            'lastPrice' => 10.0,
            'fairPrice' => 10.0,
            'risk' => 0.35,
            'trendSlope' => -0.003,
        ],
    ],
];
