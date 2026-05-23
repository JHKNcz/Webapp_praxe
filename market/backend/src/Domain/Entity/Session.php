<?php

declare(strict_types=1);

namespace Market\Domain\Entity;

final class Session
{
    public function __construct(
        private readonly string $sessionId,
        private readonly string $nickname,
        private readonly int $createdAt,
        private ?int $endedAt = null
    ) {
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->endedAt === null;
    }

    public function end(int $timestamp): void
    {
        $this->endedAt = $timestamp;
    }

    public function getEndedAt(): ?int
    {
        return $this->endedAt;
    }

    public function toArray(): array
    {
        return [
            'sessionId' => $this->sessionId,
            'nickname' => $this->nickname,
            'createdAt' => $this->createdAt,
            'endedAt' => $this->endedAt,
            'active' => $this->isActive(),
        ];
    }
}
