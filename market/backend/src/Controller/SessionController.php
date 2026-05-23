<?php

declare(strict_types=1);

namespace Market\Controller;

use Market\Application\LeaderboardService;
use Market\Application\SessionService;
use Market\Http\JsonResponse;
use Market\Http\Request;
use Market\Http\Response;

final class SessionController
{
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly LeaderboardService $leaderboardService
    ) {
    }

    public function start(Request $request, array $params = []): Response
    {
        return JsonResponse::success([
            'session' => $this->sessionService->startSession(),
        ]);
    }

    public function end(Request $request, array $params = []): Response
    {
        $sessionId = (string) $request->input('sessionId', '');
        $displayName = (string) $request->input('displayName', 'Guest');

        if ($sessionId === '') {
            return JsonResponse::error('sessionId is required', 422, 'validation_error');
        }

        $leaderboardEntry = $this->leaderboardService->record($sessionId, $displayName);
        $session = $this->sessionService->endSession($sessionId);

        return JsonResponse::success([
            'session' => $session,
            'leaderboardEntry' => $leaderboardEntry,
        ]);
    }
}
