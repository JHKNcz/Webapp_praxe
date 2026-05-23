<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\InMemory;

use Market\Domain\Entity\Session;

final class SessionRepository
{
    /** @var array<string, Session> */
    private array $sessions = [];

    public function save(Session $session): void
    {
        $this->sessions[$session->getSessionId()] = $session;
    }

    public function find(string $sessionId): ?Session
    {
        return $this->sessions[$sessionId] ?? null;
    }

    public function delete(string $sessionId): void
    {
        unset($this->sessions[$sessionId]);
    }
}
