<?php

declare(strict_types=1);

return [
    ['method' => 'POST', 'path' => '/session/start', 'controller' => 'session', 'action' => 'start'],
    ['method' => 'POST', 'path' => '/session/end', 'controller' => 'session', 'action' => 'end'],
    ['method' => 'GET', 'path' => '/assets', 'controller' => 'asset', 'action' => 'index'],
    ['method' => 'GET', 'path' => '/assets/tick', 'controller' => 'asset', 'action' => 'tick'],
    ['method' => 'GET', 'path' => '/assets/{id}', 'controller' => 'asset', 'action' => 'show'],
    ['method' => 'GET', 'path' => '/portfolio', 'controller' => 'portfolio', 'action' => 'show'],
    ['method' => 'POST', 'path' => '/orders', 'controller' => 'order', 'action' => 'store'],
    ['method' => 'GET', 'path' => '/orders', 'controller' => 'order', 'action' => 'index'],
    ['method' => 'GET', 'path' => '/orderbook/{id}', 'controller' => 'order', 'action' => 'book'],
    ['method' => 'GET', 'path' => '/leaderboard', 'controller' => 'leaderboard', 'action' => 'index'],
];
