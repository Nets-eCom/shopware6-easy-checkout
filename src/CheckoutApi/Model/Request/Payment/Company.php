<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Payment;

readonly class Company implements \JsonSerializable
{
    public function __construct(
        private string $name,
        private string $firstName,
        private string $lastName
    ) {
    }

    /**
     * @return array{
     *     name: string,
     *     contact: array{
     *         firstName: string,
     *         lastName: string
     *     }
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'contact' => [
                'firstName' => $this->firstName,
                'lastName' => $this->lastName,
            ],
        ];
    }
}
