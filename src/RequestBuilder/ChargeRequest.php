<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder;

use NexiNets\CheckoutApi\Model\Request\FullCharge;
use NexiNets\RequestBuilder\Helper\FormatHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class ChargeRequest
{
    public function __construct(private readonly FormatHelper $helper)
    {
    }

    public function buildFullCharge(OrderTransactionEntity $transaction): FullCharge
    {
        return new FullCharge(
            $this->helper->priceToInt(
                $transaction->getAmount()->getTotalPrice()
            )
        );
    }
}
