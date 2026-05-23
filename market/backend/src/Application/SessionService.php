<?php

declare(strict_types=1);

namespace Market\Application;

use DomainException;
use Market\Domain\Entity\Portfolio;
use Market\Domain\Entity\Session;
use Market\Infrastructure\Storage\InMemory\PortfolioRepository;
use Market\Infrastructure\Storage\InMemory\SessionRepository;

final class SessionService
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
        private readonly PortfolioRepository $portfolioRepository,
        private readonly float $initialCash
    ) {
    }

    public function startSession(): array
    {
        $session = new Session(bin2hex(random_bytes(16)), time());
        $this->sessionRepository->save($session);

        $portfolio = new Portfolio($this->initialCash);
        $this->portfolioRepository->save($session->getSessionId(), $portfolio);

        return $session->toArray() + [
            'cash' => $portfolio->getCash(),
        ];
    }

    public function endSession(string $sessionId): array
    {
        $session = $this->sessionRepository->find($sessionId);

        if ($session === null) {
            throw new DomainException('Session not found');
        }

        $session->end(time());
        $this->sessionRepository->save($session);

        $payload = $session->toArray();

        $this->portfolioRepository->delete($sessionId);
        $this->sessionRepository->delete($sessionId);

        return $payload;
    }
}
