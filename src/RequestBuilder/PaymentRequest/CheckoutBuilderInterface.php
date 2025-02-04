<?php declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use NexiCheckout\Model\Request\Payment\Checkout;
use Shopware\Core\Checkout\Order\OrderEntity;

interface CheckoutBuilderInterface
{
    public function create(OrderEntity $order, string $returnUrl, string $salesChannelId): Checkout;
}
