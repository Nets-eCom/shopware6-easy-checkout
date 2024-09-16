<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\Shared;

class PhoneNumber
{
    public function __construct(
        private readonly string $prefix,
        private readonly string $number
    ) {
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getNumber(): string
    {
        return $this->number;
    }
}
