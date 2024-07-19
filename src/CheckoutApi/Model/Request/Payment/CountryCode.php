<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Payment;

final readonly class CountryCode implements \JsonSerializable
{
    public function __construct(private string $code)
    {
    }

    /**
     * @return array{countryCode: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'countryCode' => $this->code,
        ];
    }
}
