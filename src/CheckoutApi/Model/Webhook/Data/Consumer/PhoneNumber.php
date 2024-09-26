<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook\Data\Consumer;

readonly class PhoneNumber
{
    public function __construct(private string $prefix, private string $number)
    {
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
