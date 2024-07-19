<?php declare(strict_types=1);

namespace NexiNets\RequestBuilder\PaymentRequest;

use NexiNets\CheckoutApi\Model\Request\Payment\Checkout;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CheckoutBuilderInterface
{
    public function create(AsyncPaymentTransactionStruct $transaction, SalesChannelContext $salesChannelContext): Checkout;
}
