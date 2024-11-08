<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder;

use NexiNets\CheckoutApi\Model\Request\FullRefundCharge;
use NexiNets\RequestBuilder\Helper\FormatHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class RefundRequest
{
    public function __construct(private readonly FormatHelper $helper)
    {
    }

    public function build(OrderTransactionEntity $transaction): FullRefundCharge
    {
        return new FullRefundCharge(
            $this->helper->priceToInt(
                $transaction->getAmount()->getTotalPrice()
            )
        );
    }
}
