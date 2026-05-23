<?php

declare(strict_types=1);

namespace Market\Controller;

use Market\Application\ActiveSessionRegistry;
use Market\Application\LeaderboardService;
use Market\Application\OrderCancellationService;
use Market\Application\SessionService;
use Market\Http\JsonResponse;
use Market\Http\Request;
use Market\Http\Response;

final class SessionController
{
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly LeaderboardService $leaderboardService,
        private readonly ActiveSessionRegistry $activeSessionRegistry,
        private readonly OrderCancellationService $orderCancellationService
    ) {
    }

    public function start(Request $request, array $params = []): Response
    {
        $nickname = (string) $request->input('nickname', '');

        if ($nickname === '') {
            return JsonResponse::error('nickname is required', 422, 'validation_error');
        }

        $session = $this->sessionService->startSession($nickname);
        $sessionId = (string) $session['sessionId'];

        $this->activeSessionRegistry->add($sessionId);
        $this->leaderboardService->syncLive($sessionId);

        return JsonResponse::success([
            'session' => $session,
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

        $this->orderCancellationService->cancelAllForSession($sessionId);

        $leaderboardEntry = $this->leaderboardService->record($sessionId, $displayName);
        $this->leaderboardService->removeLive($sessionId, $displayName);
        $this->activeSessionRegistry->remove($sessionId);
        $sessionPayload = $this->sessionService->endSession($sessionId);

        return JsonResponse::success([
            'session' => $sessionPayload,
            'leaderboardEntry' => $leaderboardEntry,
        ]);
    }

    public function resume(Request $request, array $params = []): Response
    {
        $sessionId = (string) $request->input('sessionId', '');

        if ($sessionId === '') {
            return JsonResponse::error('sessionId is required', 422, 'validation_error');
        }

        $session = $this->sessionService->getSession($sessionId);

        if ($session === null) {
            return JsonResponse::error('Session not found', 404, 'not_found');
        }

        $this->activeSessionRegistry->add($sessionId);
        $this->leaderboardService->syncLive($sessionId);

        return JsonResponse::success([
            'session' => $session->toArray(),
        ]);
    }
}
