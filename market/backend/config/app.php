<?php

declare(strict_types=1);

$debugEnv = getenv('APP_DEBUG');
$debugValue = $debugEnv === false ? false : filter_var($debugEnv, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$appDebug = $debugValue === null ? false : $debugValue;

return [
    'app_name' => 'Market',
    'app_debug' => $appDebug,
    'initial_cash' => 10000.0,
    'price_update_interval_seconds' => 10,
    'history_limit' => 20,
    'leaderboard_limit' => 10,
    'cors_allowed_origins' => ['*'],
    'seed_assets' => [
        [
            'id' => 'asset-1',
            'name' => 'Alpha Token',
            'lastPrice' => 100.0,
            'fairPrice' => 100.0,
            'risk' => 0.3,
            'trendSlope' => 0.1,
        ],
        [
            'id' => 'asset-2',
            'name' => 'Beta Corp',
            'lastPrice' => 250.0,
            'fairPrice' => 260.0,
            'risk' => 0.15,
            'trendSlope' => -0.05,
        ],
        [
            'id' => 'asset-3',
            'name' => 'Gamma Labs',
            'lastPrice' => 45.0,
            'fairPrice' => 50.0,
            'risk' => 0.6,
            'trendSlope' => 0.02,
        ],
    ],
];
