<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder;

use NexiNets\CheckoutApi\Model\Request\Cancel;
use NexiNets\RequestBuilder\Helper\FormatHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class CancelRequest
{
    public function __construct(private readonly FormatHelper $helper)
    {
    }

    public function build(OrderTransactionEntity $transaction): Cancel
    {
        return new Cancel(
            $this->helper->priceToInt(
                $transaction->getAmount()->getTotalPrice()
            )
        );
    }
}
