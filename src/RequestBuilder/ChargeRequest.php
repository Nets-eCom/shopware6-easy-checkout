<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder;

use NexiNets\CheckoutApi\Model\Request\FullCharge;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class ChargeRequest
{
    public function buildFullCharge(OrderTransactionEntity $transaction): FullCharge
    {
        return new FullCharge(
            (int) round($transaction->getAmount()->getTotalPrice() * 100), // TODO: use helper instead
        );
    }
}
