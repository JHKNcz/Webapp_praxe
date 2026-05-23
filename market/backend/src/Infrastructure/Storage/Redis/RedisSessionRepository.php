<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\Redis;

use Market\Domain\Entity\Session;

final class RedisSessionRepository
{
    public function __construct(private readonly RedisClient $redis)
    {
    }

    public function save(Session $session): void
    {
        $this->redis->hset('session:' . $session->getSessionId(), [
            'sessionId' => $session->getSessionId(),
            'nickname' => $session->getNickname(),
            'createdAt' => $session->getCreatedAt(),
            'endedAt' => $session->getEndedAt() ?? '',
        ]);
    }

    public function find(string $sessionId): ?Session
    {
        $data = $this->redis->hgetall('session:' . $sessionId);

        if ($data === [] || ($data['sessionId'] ?? '') === '') {
            return null;
        }

        $endedAt = $data['endedAt'] ?? '';
        $endedAtValue = $endedAt === '' ? null : (int) $endedAt;

        return new Session(
            (string) $data['sessionId'],
            (string) $data['nickname'],
            (int) ($data['createdAt'] ?? time()),
            $endedAtValue
        );
    }

    public function delete(string $sessionId): void
    {
        $this->redis->del('session:' . $sessionId);
    }
}
