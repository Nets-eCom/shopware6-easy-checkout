<?php declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder;

use NexiCheckout\Model\Request\ReferenceInformation;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class ReferenceInformationRequest
{
    public function build(string $checkoutUrl, OrderTransactionEntity $transaction): ReferenceInformation
    {
        $orderEntity = $transaction->getOrder();

        return new ReferenceInformation(
            $checkoutUrl,
            $orderEntity->getOrderNumber()
        );
    }
}
