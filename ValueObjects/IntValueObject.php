<?php declare(strict_types=1);

namespace olml89\Subscriptions\ValueObjects;

abstract class IntValueObject
{
    public function __construct(
        private readonly int $value,
    ) {}

    public function toInt(): int
    {
        return $this->value;
    }
}
