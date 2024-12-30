<?php declare(strict_types=1);

namespace NexiNets\RequestBuilder\PaymentRequest;

use NexiNets\CheckoutApi\Model\Request\Payment\Checkout;
use Shopware\Core\Checkout\Order\OrderEntity;

interface CheckoutBuilderInterface
{
    public function create(OrderEntity $order, string $returnUrl, string $salesChannelId): Checkout;
}
