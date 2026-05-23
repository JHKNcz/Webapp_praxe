<?php

declare(strict_types=1);

namespace Market\Controller;

use Market\Application\TradeHistoryService;
use Market\Http\JsonResponse;
use Market\Http\Request;
use Market\Http\Response;

final class TransactionController
{
    public function __construct(private readonly TradeHistoryService $tradeHistoryService)
    {
    }

    public function index(Request $request, array $params = []): Response
    {
        $sessionId = (string) $request->input('sessionId', '');
        $limit = max(1, min(100, (int) $request->input('limit', 50)));

        if ($sessionId === '') {
            return JsonResponse::error('sessionId is required', 422, 'validation_error');
        }

        return JsonResponse::success([
            'items' => $this->tradeHistoryService->list($sessionId, $limit),
        ]);
    }
}
