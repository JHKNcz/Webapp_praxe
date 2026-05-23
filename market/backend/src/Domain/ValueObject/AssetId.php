<?php

declare(strict_types=1);

namespace Market\Domain\ValueObject;

use DomainException;

final class AssetId
{
    public function __construct(private readonly string $value)
    {
        if ($value === '') {
            throw new DomainException('AssetId cannot be empty');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
