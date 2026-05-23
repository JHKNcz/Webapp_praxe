<?php

declare(strict_types=1);

namespace Market\Application;

use Market\Domain\Entity\Order;
use Market\Domain\Entity\Trade;
use Market\Infrastructure\Storage\InMemory\PortfolioRepository;

final class MatchingEngine
{
    public function __construct(
        private readonly object $orderRepository,
        private readonly PortfolioRepository $portfolioRepository,
        private readonly EventPublisher $eventPublisher
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
                $this->orderRepository->requeueFront($buyOrder);
                $this->orderRepository->requeueFront($sellOrder);
                break;
            }

            $quantity = min($buyOrder->getRemainingQty(), $sellOrder->getRemainingQty());
            $buyPortfolio = $this->portfolioRepository->find($buyOrder->getSessionId());
            $sellPortfolio = $this->portfolioRepository->find($sellOrder->getSessionId());

            if ($buyPortfolio === null || $sellPortfolio === null) {
                if ($buyOrder !== null) {
                    $this->orderRepository->requeueFront($buyOrder);
                }

                if ($sellOrder !== null) {
                    $this->orderRepository->requeueFront($sellOrder);
                }

                break;
            }

            $tradeCost = round($quantity * $currentPrice, 2);
            $buyLocked = round($quantity * $buyOrder->getPrice(), 2);
            $extraCost = round($tradeCost - $buyLocked, 2);

            if ($extraCost > 0 && $buyPortfolio->getCash() < $extraCost) {
                $this->orderRepository->requeueFront($buyOrder);
                $this->orderRepository->requeueFront($sellOrder);
                break;
            }

            if ($extraCost > 0) {
                $buyPortfolio->setCash($buyPortfolio->getCash() - $extraCost);
            } elseif ($extraCost < 0) {
                $buyPortfolio->setCash($buyPortfolio->getCash() + abs($extraCost));
            }

            $buyPortfolio->creditShares($buyOrder->getAssetId(), $quantity, $currentPrice);
            $sellPortfolio->creditCash($tradeCost);

            $buyOrder->fill($quantity);
            $sellOrder->fill($quantity);

            $this->orderRepository->save($buyOrder);
            $this->orderRepository->save($sellOrder);
            $this->portfolioRepository->save($buyOrder->getSessionId(), $buyPortfolio);
            $this->portfolioRepository->save($sellOrder->getSessionId(), $sellPortfolio);

            $trade = Trade::create($buyOrder, $sellOrder, $currentPrice, $quantity);
            $tradePayload = $trade->toArray();
            $trades[] = $tradePayload;
            $this->eventPublisher->publishTrade($tradePayload);

            if ($buyOrder->isOpen()) {
                $this->orderRepository->requeueFront($buyOrder);
            }

            if ($sellOrder->isOpen()) {
                $this->orderRepository->requeueFront($sellOrder);
            }
        }

        return $trades;
    }
}
