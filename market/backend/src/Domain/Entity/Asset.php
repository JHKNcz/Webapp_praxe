<?php

declare(strict_types=1);

namespace Market\Domain\Entity;

final class Asset
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private float $lastPrice,
        private float $fairPrice,
        private float $risk,
        private float $trendSlope,
        private float $currentSlope = 0.0,
        private string $phase = 'normal',
        private int $phaseTicksRemaining = 0,
        private int $phaseTotalDuration = 0,
    ) {
        $this->fairPrice = round(max(1.0, $this->fairPrice), 2);
        $this->risk = min(1.0, max(0.0, $this->risk));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLastPrice(): float
    {
        return $this->lastPrice;
    }

    public function setLastPrice(float $p): void
    {
        $this->lastPrice = round(max(1.0, $p), 2);
    }

    public function getFairPrice(): float
    {
        return round($this->fairPrice, 2);
    }

    public function setFairPrice(float $p): void
    {
        $this->fairPrice = round(max(1.0, $p), 2);
    }

    public function getRisk(): float
    {
        return $this->risk;
    }

    public function getTrendSlope(): float
    {
        return $this->trendSlope;
    }

    public function getCurrentSlope(): float
    {
        return $this->currentSlope;
    }

    public function setCurrentSlope(float $s): void
    {
        $this->currentSlope = $s;
    }

    public function getPhase(): string
    {
        return $this->phase;
    }

    public function getPhaseTicksRemaining(): int
    {
        return $this->phaseTicksRemaining;
    }

    public function getPhaseTotalDuration(): int
    {
        return $this->phaseTotalDuration;
    }

    public function setPhase(string $phase, int $duration): void
    {
        $this->phase = $phase;
        $this->phaseTicksRemaining = $duration;
        $this->phaseTotalDuration = $duration;
    }

    public function setPhaseTicksRemaining(int $remaining): void
    {
        $this->phaseTicksRemaining = $remaining;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lastPrice' => round($this->lastPrice, 2),
            'phase' => $this->phase,
        ];
    }
}
