<?php

declare(strict_types=1);

namespace Market\Controller;

use Market\Application\TradeService;
use Market\Http\JsonResponse;
use Market\Http\Request;
use Market\Http\Response;

final class TradeController
{
    public function __construct(private readonly TradeService $tradeService)
    {
    }

    public function buy(Request $request, array $params = []): Response
    {
        $sessionId = (string) $request->input('sessionId', '');
        $assetId = (string) $request->input('assetId', '');
        $quantity = (int) $request->input('quantity', 0);

        if ($sessionId === '' || $assetId === '' || $quantity <= 0) {
            return JsonResponse::error('sessionId, assetId and quantity are required', 422, 'validation_error');
        }

        return JsonResponse::success([
            'trade' => $this->tradeService->buy($sessionId, $assetId, $quantity),
        ]);
    }

    public function sell(Request $request, array $params = []): Response
    {
        $sessionId = (string) $request->input('sessionId', '');
        $assetId = (string) $request->input('assetId', '');
        $quantity = (int) $request->input('quantity', 0);

        if ($sessionId === '' || $assetId === '' || $quantity <= 0) {
            return JsonResponse::error('sessionId, assetId and quantity are required', 422, 'validation_error');
        }

        return JsonResponse::success([
            'trade' => $this->tradeService->sell($sessionId, $assetId, $quantity),
        ]);
    }
}
