<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder;

use NexiNets\CheckoutApi\Model\Request\FullRefundCharge;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class RefundRequest
{
    public function build(OrderTransactionEntity $transaction): FullRefundCharge
    {
        return new FullRefundCharge(
            (int) round($transaction->getAmount()->getTotalPrice() * 100), // TODO: use helper instead
        );
    }
}
