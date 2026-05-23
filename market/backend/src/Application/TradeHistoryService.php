<?php

declare(strict_types=1);

namespace Market\Application;

use Market\Domain\Entity\TransactionEntry;

final class TradeHistoryService
{
    public function __construct(
        private readonly object $tradeHistoryRepository,
        private readonly object $sessionRepository
    ) {
    }

    /** @param array<string, mixed> $trade */
    public function recordMarketTrade(array $trade): void
    {
        $sessionId = (string) ($trade['sessionId'] ?? '');
        $side = (string) ($trade['side'] ?? '');

        if ($sessionId === '' || $side === '') {
            return;
        }

        $this->append(new TransactionEntry(
            (string) ($trade['id'] ?? bin2hex(random_bytes(8))),
            $sessionId,
            $side,
            (string) ($trade['assetId'] ?? ''),
            (int) ($trade['quantity'] ?? 0),
            (float) ($trade['price'] ?? 0.0),
            'market',
            'exchange',
            (int) ($trade['timestamp'] ?? time())
        ));
    }

    /** @param array<string, mixed> $trade P2P trade payload from Trade::toArray() */
    public function recordP2pTrade(array $trade): void
    {
        $buySessionId = (string) ($trade['buySessionId'] ?? '');
        $sellSessionId = (string) ($trade['sellSessionId'] ?? '');
        $assetId = (string) ($trade['assetId'] ?? '');
        $price = (float) ($trade['price'] ?? 0.0);
        $quantity = (int) ($trade['quantity'] ?? 0);
        $timestamp = (int) ($trade['timestamp'] ?? time());
        $tradeId = (string) ($trade['id'] ?? bin2hex(random_bytes(8)));

        $buyNickname = $this->nicknameFor($buySessionId);
        $sellNickname = $this->nicknameFor($sellSessionId);

        $this->append(new TransactionEntry(
            $tradeId . ':buy',
            $buySessionId,
            'buy',
            $assetId,
            $quantity,
            $price,
            'p2p',
            $sellNickname,
            $timestamp
        ));

        $this->append(new TransactionEntry(
            $tradeId . ':sell',
            $sellSessionId,
            'sell',
            $assetId,
            $quantity,
            $price,
            'p2p',
            $buyNickname,
            $timestamp
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function list(string $sessionId, int $limit = 50): array
    {
        return array_map(
            static fn (TransactionEntry $entry): array => $entry->toArray(),
            $this->tradeHistoryRepository->list($sessionId, $limit)
        );
    }

    private function append(TransactionEntry $entry): void
    {
        $this->tradeHistoryRepository->append($entry);
    }

    private function nicknameFor(string $sessionId): string
    {
        $session = $this->sessionRepository->find($sessionId);

        return $session?->getNickname() ?? 'Guest';
    }
}
