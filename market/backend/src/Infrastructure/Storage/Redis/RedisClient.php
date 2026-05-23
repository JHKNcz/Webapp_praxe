<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\Redis;

use Predis\Client;

final class RedisClient
{
    private Client $client;

    public function __construct(string $url, private readonly string $prefix = 'market:')
    {
        $this->client = new Client($url);
    }

    public function key(string $name): string
    {
        return $this->prefix . $name;
    }

    public function lpush(string $key, string ...$values): int
    {
        return $this->client->lpush($this->key($key), $values);
    }

    public function rpop(string $key): ?string
    {
        $value = $this->client->rpop($this->key($key));

        return is_string($value) ? $value : null;
    }

    public function llen(string $key): int
    {
        return (int) $this->client->llen($this->key($key));
    }

    public function lrange(string $key, int $start, int $stop): array
    {
        $values = $this->client->lrange($this->key($key), $start, $stop);

        return is_array($values) ? $values : [];
    }

    public function ltrim(string $key, int $start, int $stop): void
    {
        $this->client->ltrim($this->key($key), $start, $stop);
    }

    public function lrem(string $key, int $count, string $value): void
    {
        $this->client->lrem($this->key($key), $count, $value);
    }

    /** @param array<string, float|int|string|null> $fields */
    public function hset(string $key, array $fields): void
    {
        $payload = [];
        foreach ($fields as $field => $value) {
            $payload[$field] = $value === null ? '' : (string) $value;
        }

        $this->client->hmset($this->key($key), $payload);
    }

    /** @return array<string, string> */
    public function hgetall(string $key): array
    {
        $values = $this->client->hgetall($this->key($key));

        return is_array($values) ? $values : [];
    }

    public function del(string $key): void
    {
        $this->client->del([$this->key($key)]);
    }

    public function zadd(string $key, float $score, string $member): void
    {
        $this->client->zadd($this->key($key), [$member => $score]);
    }

    public function zrem(string $key, string ...$members): void
    {
        if ($members === []) {
            return;
        }

        $this->client->zrem($this->key($key), $members);
    }

    /** @return array<int, array{member: string, score: float}> */
    public function zrevrangeWithScores(string $key, int $start, int $stop): array
    {
        $raw = $this->client->zrevrange($this->key($key), $start, $stop, ['WITHSCORES' => true]);

        if (!is_array($raw)) {
            return [];
        }

        $entries = [];
        foreach ($raw as $member => $score) {
            $entries[] = [
                'member' => (string) $member,
                'score' => (float) $score,
            ];
        }

        return $entries;
    }

    public function publish(string $channel, string $message): void
    {
        $this->client->publish($channel, $message);
    }

    public function sadd(string $key, string ...$members): void
    {
        if ($members === []) {
            return;
        }

        $this->client->sadd($this->key($key), $members);
    }

    public function srem(string $key, string ...$members): void
    {
        if ($members === []) {
            return;
        }

        $this->client->srem($this->key($key), $members);
    }

    /** @return array<int, string> */
    public function smembers(string $key): array
    {
        $members = $this->client->smembers($this->key($key));

        return is_array($members) ? array_map('strval', $members) : [];
    }

    public function set(string $key, string $value): void
    {
        $this->client->set($this->key($key), $value);
    }

    public function get(string $key): ?string
    {
        $value = $this->client->get($this->key($key));

        return is_string($value) ? $value : null;
    }

    public function ping(): bool
    {
        try {
            $result = $this->client->ping();

            if (is_object($result) && method_exists($result, 'getPayload')) {
                return $result->getPayload() === 'PONG';
            }

            return $result === 'PONG' || $result === true;
        } catch (\Throwable) {
            return false;
        }
    }
}
