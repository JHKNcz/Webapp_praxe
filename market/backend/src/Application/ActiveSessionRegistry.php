<?php

declare(strict_types=1);

namespace Market\Application;

use Market\Infrastructure\Storage\Redis\RedisClient;

final class ActiveSessionRegistry
{
    private const KEY = 'sessions:active';

    /** @var array<string, true> */
    private array $sessions = [];

    public function __construct(private readonly ?RedisClient $redis = null)
    {
    }

    public function add(string $sessionId): void
    {
        if ($this->redis !== null) {
            $this->redis->sadd(self::KEY, $sessionId);
            return;
        }

        $this->sessions[$sessionId] = true;
    }

    public function remove(string $sessionId): void
    {
        if ($this->redis !== null) {
            $this->redis->srem(self::KEY, $sessionId);
            return;
        }

        unset($this->sessions[$sessionId]);
    }

    public function contains(string $sessionId): bool
    {
        if ($this->redis !== null) {
            return in_array($sessionId, $this->redis->smembers(self::KEY), true);
        }

        return isset($this->sessions[$sessionId]);
    }

    /** @return array<int, string> */
    public function all(): array
    {
        if ($this->redis !== null) {
            return $this->redis->smembers(self::KEY);
        }

        return array_keys($this->sessions);
    }
}
