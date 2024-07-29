<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Payment;

readonly class PrivatePerson implements \JsonSerializable
{
    public function __construct(
        private string $firstName,
        private string $lastName
    ) {
    }

    /**
     * @return array{
     *     firstName: string,
     *     lastName: string,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
        ];
    }
}
