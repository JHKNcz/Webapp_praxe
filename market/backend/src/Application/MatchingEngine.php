<?php

declare(strict_types=1);

namespace Market\Application;

use DomainException;
use Market\Domain\Entity\Order;
use Market\Domain\Entity\Portfolio;
use Market\Domain\Entity\Trade;

final class MatchingEngine
{
    public function __construct(
        private readonly object $orderRepository,
        private readonly object $portfolioRepository,
        private readonly EventPublisher $eventPublisher,
        private readonly ?TradeHistoryService $tradeHistoryService = null
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function match(string $assetId, float $currentPrice): array
    {
        $trades = [];

        while (true) {
            $buyOrder = $this->orderRepository->dequeue($assetId, 'buy');
            $sellOrder = $this->orderRepository->dequeue($assetId, 'sell');

            if ($buyOrder === null || $sellOrder === null) {
                if ($buyOrder !== null) {
                    $this->orderRepository->requeueFront($buyOrder);
                }

                if ($sellOrder !== null) {
                    $this->orderRepository->requeueFront($sellOrder);
                }

                break;
            }

            if ($buyOrder->getSessionId() === $sellOrder->getSessionId()) {
                $hasOtherSessionLiquidity = false;

                foreach ($this->orderRepository->listOpenForAsset($buyOrder->getAssetId()) as $queuedOrder) {
                    if ($queuedOrder->getSessionId() !== $buyOrder->getSessionId()) {
                        $hasOtherSessionLiquidity = true;
                        break;
                    }
                }

                if (!$hasOtherSessionLiquidity) {
                    $this->orderRepository->requeueFront($buyOrder);
                    $this->orderRepository->requeueFront($sellOrder);
                    break;
                }

                $this->orderRepository->requeueBack($buyOrder);
                $this->orderRepository->requeueBack($sellOrder);
                continue;
            }

            if ($buyOrder->getPrice() < $sellOrder->getPrice()) {
                $this->orderRepository->requeueFront($buyOrder);
                $this->orderRepository->requeueFront($sellOrder);
                break;
            }

            $fillPrice = $sellOrder->getPrice();
            $trade = $this->executeQueuedPair($buyOrder, $sellOrder, $fillPrice);

            if ($trade === null) {
                $this->orderRepository->requeueFront($buyOrder);
                $this->orderRepository->requeueFront($sellOrder);
                break;
            }

            $trades[] = $trade;
        }

        return $trades;
    }

    /** @return array<string, mixed> */
    public function takeOrder(string $takerSessionId, string $orderId, int $quantity): array
    {
        $makerOrder = $this->orderRepository->find($orderId);

        if ($makerOrder === null || !$makerOrder->isOpen()) {
            throw new DomainException('Order not found or already filled');
        }

        if ($makerOrder->getSessionId() === $takerSessionId) {
            throw new DomainException('Cannot take your own order');
        }

        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero');
        }

        $quantity = min($quantity, $makerOrder->getRemainingQty());
        $price = $makerOrder->getPrice();
        $assetId = $makerOrder->getAssetId();

        $takerPortfolio = $this->portfolioRepository->find($takerSessionId);
        $makerPortfolio = $this->portfolioRepository->find($makerOrder->getSessionId());

        if ($takerPortfolio === null || $makerPortfolio === null) {
            throw new DomainException('Portfolio not found');
        }

        if ($makerOrder->getSide() === 'sell') {
            $takerPortfolio->buy($assetId, $quantity, $price);
            $makerPortfolio->settleLockedSellFill($quantity, $price);
            $buySessionId = $takerSessionId;
            $sellSessionId = $makerOrder->getSessionId();
        } else {
            $takerPortfolio->sell($assetId, $quantity, $price);
            $makerPortfolio->settleLockedBuyFill($assetId, $quantity, $price, $makerOrder->getPrice());
            $buySessionId = $makerOrder->getSessionId();
            $sellSessionId = $takerSessionId;
        }

        $makerOrder->fill($quantity);
        $this->orderRepository->save($makerOrder);
        $this->portfolioRepository->save($takerSessionId, $takerPortfolio);
        $this->portfolioRepository->save($makerOrder->getSessionId(), $makerPortfolio);

        if ($makerOrder->isOpen()) {
            $this->orderRepository->save($makerOrder);
        } else {
            $this->orderRepository->removeFromQueue($makerOrder);
            $this->releaseRemainingCollateral($makerOrder, $makerPortfolio);
        }

        $trade = Trade::fromSessions(
            $buySessionId,
            $sellSessionId,
            $assetId,
            $price,
            $quantity,
            $buySessionId === $makerOrder->getSessionId() ? $makerOrder->getId() : '',
            $sellSessionId === $makerOrder->getSessionId() ? $makerOrder->getId() : ''
        );
        $tradePayload = $trade->toArray();
        $this->eventPublisher->publishTrade($tradePayload);
        $this->tradeHistoryService?->recordP2pTrade($tradePayload);

        return $tradePayload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function executeQueuedPair(Order $buyOrder, Order $sellOrder, float $fillPrice): ?array
    {
        $quantity = min($buyOrder->getRemainingQty(), $sellOrder->getRemainingQty());
        $buyPortfolio = $this->portfolioRepository->find($buyOrder->getSessionId());
        $sellPortfolio = $this->portfolioRepository->find($sellOrder->getSessionId());

        if ($buyPortfolio === null || $sellPortfolio === null) {
            return null;
        }

        try {
            $buyPortfolio->settleLockedBuyFill(
                $buyOrder->getAssetId(),
                $quantity,
                $fillPrice,
                $buyOrder->getPrice()
            );
            $sellPortfolio->settleLockedSellFill($quantity, $fillPrice);
        } catch (DomainException) {
            return null;
        }

        $buyOrder->fill($quantity);
        $sellOrder->fill($quantity);

        $this->orderRepository->save($buyOrder);
        $this->orderRepository->save($sellOrder);
        $this->portfolioRepository->save($buyOrder->getSessionId(), $buyPortfolio);
        $this->portfolioRepository->save($sellOrder->getSessionId(), $sellPortfolio);

        if ($buyOrder->isOpen()) {
            $this->orderRepository->requeueFront($buyOrder);
        } else {
            $this->orderRepository->removeFromQueue($buyOrder);
            $this->releaseRemainingCollateral($buyOrder, $buyPortfolio);
        }

        if ($sellOrder->isOpen()) {
            $this->orderRepository->requeueFront($sellOrder);
        } else {
            $this->orderRepository->removeFromQueue($sellOrder);
            $this->releaseRemainingCollateral($sellOrder, $sellPortfolio);
        }

        $trade = Trade::create($buyOrder, $sellOrder, $fillPrice, $quantity);
        $tradePayload = $trade->toArray();
        $this->eventPublisher->publishTrade($tradePayload);
        $this->tradeHistoryService?->recordP2pTrade($tradePayload);

        return $tradePayload;
    }

    private function releaseRemainingCollateral(Order $order, Portfolio $portfolio): void
    {
        $portfolio->releaseOrderCollateral(
            $order->getSide(),
            $order->getAssetId(),
            $order->getRemainingQty(),
            $order->getPrice()
        );
    }
}
