<?php

declare(strict_types=1);

namespace Market\Controller;

use Market\Application\PortfolioService;
use Market\Http\JsonResponse;
use Market\Http\Request;
use Market\Http\Response;

final class PortfolioController
{
    public function __construct(private readonly PortfolioService $portfolioService)
    {
    }

    public function show(Request $request, array $params = []): Response
    {
        $sessionId = (string) $request->input('sessionId', '');

        if ($sessionId === '') {
            return JsonResponse::error('sessionId is required', 422, 'validation_error');
        }

        return JsonResponse::success([
            'portfolio' => $this->portfolioService->getPortfolioSummary($sessionId),
        ]);
    }
}
