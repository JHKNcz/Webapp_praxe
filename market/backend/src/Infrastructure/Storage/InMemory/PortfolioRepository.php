<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\InMemory;

use Market\Domain\Entity\Portfolio;

final class PortfolioRepository
{
    /** @var array<string, Portfolio> */
    private array $portfolios = [];

    public function save(string $sessionId, Portfolio $portfolio): void
    {
        $this->portfolios[$sessionId] = $portfolio;
    }

    public function find(string $sessionId): ?Portfolio
    {
        return $this->portfolios[$sessionId] ?? null;
    }

    public function delete(string $sessionId): void
    {
        unset($this->portfolios[$sessionId]);
    }
}
