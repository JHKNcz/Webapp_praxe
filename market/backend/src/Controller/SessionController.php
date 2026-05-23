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
        $nickname = (string) $request->input('nickname', '');

        if ($nickname === '') {
            return JsonResponse::error('nickname is required', 422, 'validation_error');
        }

        return JsonResponse::success([
            'session' => $this->sessionService->startSession($nickname),
        ]);
    }

    public function end(Request $request, array $params = []): Response
    {
        $sessionId = (string) $request->input('sessionId', '');

        if ($sessionId === '') {
            return JsonResponse::error('sessionId is required', 422, 'validation_error');
        }

        $session = $this->sessionService->getSession($sessionId);
        $displayName = $session?->getNickname() ?? 'Guest';

        $leaderboardEntry = $this->leaderboardService->record($sessionId, $displayName);
        $sessionPayload = $this->sessionService->endSession($sessionId);

        return JsonResponse::success([
            'session' => $sessionPayload,
            'leaderboardEntry' => $leaderboardEntry,
        ]);
    }
}
