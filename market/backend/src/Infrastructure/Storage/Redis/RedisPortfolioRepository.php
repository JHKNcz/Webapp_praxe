<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\Redis;

use Market\Domain\Entity\Portfolio;

final class RedisPortfolioRepository
{
    public function __construct(private readonly RedisClient $redis)
    {
    }

    public function save(string $sessionId, Portfolio $portfolio): void
    {
        $json = json_encode($portfolio->toArray(), JSON_THROW_ON_ERROR);
        $this->redis->set('portfolio:' . $sessionId, $json);
    }

    public function find(string $sessionId): ?Portfolio
    {
        $json = $this->redis->get('portfolio:' . $sessionId);

        if ($json === null || $json === '') {
            return null;
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            return null;
        }

        return Portfolio::fromStorage($data);
    }

    public function delete(string $sessionId): void
    {
        $this->redis->del('portfolio:' . $sessionId);
    }
}
