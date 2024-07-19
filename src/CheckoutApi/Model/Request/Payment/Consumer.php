<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Payment;

final readonly class Consumer implements \JsonSerializable
{
    public function __construct(
        private string $email,
        private ?string $reference,
        private ?Address $shippingAddress,
        private ?Address $billingAddress,
        private ?PhoneNumber $phoneNumber = null,
        private ?PrivatePerson $privatePerson = null,
        private ?Company $company = null,
    ) {
    }

    /**
     * @return array{
     *     email: string,
     *     reference: ?string,
     *     shippingAddress: ?Address,
     *     billingAddress: ?Address,
     *     phoneNumber: ?PhoneNumber,
     *     privatePerson: ?PrivatePerson,
     *     company: ?Company,
     * }
     */
    public function jsonSerialize(): mixed
    {
        return [
            'email' => $this->email,
            'reference' => $this->reference,
            'shippingAddress' => $this->shippingAddress,
            'billingAddress' => $this->billingAddress,
            'phoneNumber' => $this->phoneNumber,
            'privatePerson' => $this->privatePerson,
            'company' => $this->company,
        ];
    }
}
