<?php

declare(strict_types=1);

namespace Market\Http;

use DomainException;
use Throwable;

final class Router
{
    /** @var array<int, array{method: string, pattern: string, regex: string, handler: callable}> */
    private array $routes = [];

    public function __construct(private readonly bool $debug = false)
    {
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => $this->compilePattern($pattern),
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $path = rtrim($request->path(), '/') ?: '/';
        $method = $request->method();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            try {
                $response = ($route['handler'])($request, $params);

                if ($response instanceof Response) {
                    return $response;
                }

                return JsonResponse::success(is_array($response) ? $response : ['data' => $response]);
            } catch (DomainException $exception) {
                return JsonResponse::error($exception->getMessage(), 400, 'domain_error');
            } catch (Throwable $exception) {
                return JsonResponse::error(
                    'Unexpected server error',
                    500,
                    'server_error',
                    $this->debug ? ['debug' => $exception->getMessage()] : []
                );
            }
        }

        if ($pathMatched) {
            return JsonResponse::error('Method not allowed', 405, 'method_not_allowed');
        }

        return JsonResponse::error('Route not found', 404, 'not_found');
    }

    private function compilePattern(string $pattern): string
    {
        $pattern = rtrim($pattern, '/') ?: '/';
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);

        return '#^' . $pattern . '$#';
    }
}
