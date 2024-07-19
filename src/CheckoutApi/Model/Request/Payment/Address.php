<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Payment;

readonly class Address implements \JsonSerializable
{
    public function __construct(
        private ?string $addressLine1,
        private ?string $addressLine2,
        private ?string $postalCode,
        private ?string $city,
        private ?string $country,
    ) {
    }

    /**
     * @return string[]
     */
    public function jsonSerialize(): array
    {
        $result = [
            'addressLine1' => $this->addressLine1,
            'addressLine2' => $this->addressLine2,
            'postalCode' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
        ];

        return array_filter($result);
    }
}
