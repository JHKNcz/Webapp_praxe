<?php

declare(strict_types=1);

namespace Market\Domain\ValueObject;

use DomainException;

final class Money
{
    public function __construct(private float $amount)
    {
        if ($amount < 0) {
            throw new DomainException('Money cannot be negative');
        }

        $this->amount = round($amount, 2);
    }

    public function amount(): float
    {
        return round($this->amount, 2);
    }

    public function add(float $amount): self
    {
        return new self($this->amount + $amount);
    }

    public function subtract(float $amount): self
    {
        $next = $this->amount - $amount;

        if ($next < 0) {
            throw new DomainException('Money cannot be negative');
        }

        return new self($next);
    }
}
