<?php

declare(strict_types=1);

namespace Market\Http;

class Response
{
    /** @var array<string, string> */
    protected array $headers = [];

    public function __construct(
        protected string $body = '',
        protected int $statusCode = 200
    ) {
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function status(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
