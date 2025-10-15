<?php

declare(strict_types=1);

namespace Nexi\Checkout\Administration\Model;

use Shopware\Core\Framework\Context;
use Symfony\Component\Validator\Constraints as Assert;

class RefundData
{
    private Context $context;

    /**
     * @param array<string, array{amount: float, items: array<ChargeItem>}> $charges
     */
    public function __construct(
        #[Assert\GreaterThanOrEqual(value: 0.01, message: 'nexi-checkout-payment-component.validation.errors.refund_amount')]
        private readonly float $amount,
        #[Assert\Valid]
        private readonly array $charges = [],
    ) {
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return array<string, array{amount: float, items: array<ChargeItem>}>
     */
    public function getCharges(): array
    {
        return $this->charges;
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
