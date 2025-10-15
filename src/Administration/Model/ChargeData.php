<?php

declare(strict_types=1);

namespace Nexi\Checkout\Administration\Model;

use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Cascade]
class ChargeData
{
    /**
     * @var Item[]
     */
    #[Assert\All([new Assert\Type(Item::class)])]
    private readonly array $items;

    /**
     * @param array{'reference': string, 'quantity': int|string, 'amount': float|string}[] $items
     */
    public function __construct(
        #[Assert\GreaterThanOrEqual(value: 0.01, message: 'nexi-checkout-payment-component.validation.errors.charge_amount')]
        private readonly float $amount,
        array $items = []
    ) {
        $this->items = array_map(
            fn ($item) => new Item($item['reference'], (int) $item['quantity'], (float) $item['amount']),
            $items
        );
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return Item[]
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
