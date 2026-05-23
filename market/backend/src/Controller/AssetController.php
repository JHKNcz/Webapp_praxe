<?php

declare(strict_types=1);

namespace Market\Controller;

use Market\Application\AssetService;
use Market\Http\JsonResponse;
use Market\Http\Request;
use Market\Http\Response;

final class AssetController
{
    public function __construct(private readonly AssetService $assetService)
    {
    }

    public function index(Request $request, array $params = []): Response
    {
        return JsonResponse::success([
            'items' => $this->assetService->listAssets(),
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $assetId = (string) ($params['id'] ?? '');
        $limit = (int) $request->input('limit', 20);

        $asset = $this->assetService->getAsset($assetId);

        if ($asset === null) {
            return JsonResponse::error('Asset not found', 404, 'asset_not_found');
        }

        return JsonResponse::success([
            'item' => $asset,
            'history' => $this->assetService->getHistory($assetId, $limit),
        ]);
    }

    public function tick(Request $request, array $params = []): Response
    {
        return JsonResponse::success([
            'items' => $this->assetService->tick(),
        ]);
    }
}
