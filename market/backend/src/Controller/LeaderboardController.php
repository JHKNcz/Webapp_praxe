<?php

declare(strict_types=1);

namespace Market\Controller;

use Market\Application\LeaderboardService;
use Market\Http\JsonResponse;
use Market\Http\Request;
use Market\Http\Response;

final class LeaderboardController
{
    public function __construct(private readonly LeaderboardService $leaderboardService)
    {
    }

    public function index(Request $request, array $params = []): Response
    {
        $limit = (int) $request->input('limit', 10);

        return JsonResponse::success([
            'items' => $this->leaderboardService->top($limit),
        ]);
    }
}
