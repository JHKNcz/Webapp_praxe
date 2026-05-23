<?php

declare(strict_types=1);

namespace Market\Application;

final class EventPublisher
{
    public const CHANNEL_PRICES = 'market:prices';
    public const CHANNEL_TRADES = 'market:trades';
    public const CHANNEL_LEADERBOARD = 'market:leaderboard';
    public const CHANNEL_ORDERBOOK = 'market:orderbook';

    public function __construct(private readonly ?object $redisClient = null)
    {
    }

    public function publishPriceTick(array $assets): void
    {
        $this->publish(self::CHANNEL_PRICES, [
            'type' => 'price_tick',
            'items' => $assets,
            'ts' => time(),
        ]);
    }

    public function publishTrade(array $trade): void
    {
        $this->publish(self::CHANNEL_TRADES, [
            'type' => 'trade',
            'trade' => $trade,
            'ts' => time(),
        ]);
    }

    public function publishLeaderboard(array $top): void
    {
        $this->publish(self::CHANNEL_LEADERBOARD, [
            'type' => 'leaderboard_update',
            'items' => $top,
            'ts' => time(),
        ]);
    }

    public function publishOrderBook(string $assetId, array $orderbook): void
    {
        $this->publish(self::CHANNEL_ORDERBOOK, [
            'type' => 'orderbook_update',
            'assetId' => $assetId,
            'orderbook' => $orderbook,
            'ts' => time(),
        ]);
    }

    private function publish(string $channel, array $payload): void
    {
        if ($this->redisClient === null) {
            return;
        }

        $message = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($message === false) {
            return;
        }

        $this->redisClient->publish($channel, $message);
    }
}
