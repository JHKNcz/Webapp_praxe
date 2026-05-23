<?php

declare(strict_types=1);

namespace Market\Http;

final class JsonResponse extends Response
{
    public function __construct(array $payload, int $statusCode = 200)
    {
        parent::__construct(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            $statusCode
        );

        $this->header('Content-Type', 'application/json; charset=utf-8');
    }

    public static function success(array $payload, int $statusCode = 200): self
    {
        return new self(['ok' => true] + $payload, $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, string $code = 'error', array $extra = []): self
    {
        return new self([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ] + $extra,
        ], $statusCode);
    }
}
