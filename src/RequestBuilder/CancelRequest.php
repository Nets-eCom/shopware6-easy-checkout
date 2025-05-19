<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder;

use Nexi\Checkout\Helper\FormatHelper;
use NexiCheckout\Model\Request\Cancel;
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
