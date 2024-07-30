<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request;

use NexiNets\CheckoutApi\Model\Request\Charge\Shipping;

abstract class Charge implements \JsonSerializable
{
    public function __construct(
        protected bool $finalCharge = true,
        protected ?Shipping $shipping = null,
        protected ?string $myReference = null,
        protected ?string $paymentMethodReference = null
    ) {
    }

    abstract public function getAmount(): int;

    /**
     * @return array{
     *     amount: int,
     *     finalCharge: bool,
     *     shipping: ?Shipping,
     *     myReference: ?string,
     *     paymentMethodReference: ?string
     * }
     */
    public function jsonSerialize(): array
    {
        $result = [
            'amount' => $this->getAmount(),
            'finalCharge' => $this->finalCharge,
        ];

        if ($this->shipping instanceof Shipping) {
            $result['shipping'] = $this->shipping;
        }

        if ($this->myReference !== null) {
            $result['myReference'] = $this->myReference;
        }

        if ($this->paymentMethodReference !== null) {
            $result['paymentMethodReference'] = $this->paymentMethodReference;
        }

        return $result;
    }
}
