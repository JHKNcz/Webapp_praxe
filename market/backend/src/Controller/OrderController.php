<?php

declare(strict_types=1);

namespace Market\Controller;

use Market\Application\OrderService;
use Market\Http\JsonResponse;
use Market\Http\Request;
use Market\Http\Response;

final class OrderController
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function store(Request $request, array $params = []): Response
    {
        $sessionId = (string) $request->input('sessionId', '');
        $assetId = (string) $request->input('assetId', '');
        $side = (string) $request->input('side', '');
        $quantity = (int) $request->input('quantity', 0);

        if ($sessionId === '' || $assetId === '' || $side === '' || $quantity <= 0) {
            return JsonResponse::error('sessionId, assetId, side and quantity are required', 422, 'validation_error');
        }

        return JsonResponse::success([
            'result' => $this->orderService->placeOrder($sessionId, $assetId, $side, $quantity),
        ]);
    }

    public function index(Request $request, array $params = []): Response
    {
        $sessionId = (string) $request->input('sessionId', '');

        if ($sessionId === '') {
            return JsonResponse::error('sessionId is required', 422, 'validation_error');
        }

        return JsonResponse::success([
            'items' => $this->orderService->getOpenOrders($sessionId),
        ]);
    }

    public function book(Request $request, array $params = []): Response
    {
        $assetId = (string) ($params['id'] ?? '');

        if ($assetId === '') {
            return JsonResponse::error('assetId is required', 422, 'validation_error');
        }

        return JsonResponse::success([
            'orderbook' => $this->orderService->getOrderBook($assetId),
        ]);
    }
}
