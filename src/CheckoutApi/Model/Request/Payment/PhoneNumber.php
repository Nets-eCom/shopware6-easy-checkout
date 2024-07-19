<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Payment;

readonly class PhoneNumber implements \JsonSerializable
{
    public function __construct(
        private readonly string $prefix,
        private readonly string $number
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'prefix' => $this->prefix,
            'number' => $this->number,
        ];
    }
}
