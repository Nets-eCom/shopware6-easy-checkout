<?php declare(strict_types=1);

namespace NexiNets\RequestBuilder\PaymentRequest;

use NexiNets\CheckoutApi\Model\Request\Payment\Checkout;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

interface CheckoutBuilderInterface
{
    public function create(OrderTransactionEntity $transaction, string $returnUrl, string $salesChannelId): Checkout;
}
