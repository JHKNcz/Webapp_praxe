<?php

declare(strict_types=1);

namespace Market\Infrastructure\Storage\InMemory;

use Market\Domain\Entity\TransactionEntry;

final class TradeHistoryRepository
{
    private const MAX_ENTRIES = 100;

    /** @var array<string, list<TransactionEntry>> */
    private array $bySession = [];

    public function append(TransactionEntry $entry): void
    {
        $sessionId = $entry->getSessionId();
        $this->bySession[$sessionId] ??= [];
        array_unshift($this->bySession[$sessionId], $entry);

        if (count($this->bySession[$sessionId]) > self::MAX_ENTRIES) {
            $this->bySession[$sessionId] = array_slice($this->bySession[$sessionId], 0, self::MAX_ENTRIES);
        }
    }

    /** @return array<int, TransactionEntry> */
    public function list(string $sessionId, int $limit = 50): array
    {
        $entries = $this->bySession[$sessionId] ?? [];

        return array_slice($entries, 0, max(0, $limit));
    }
}
