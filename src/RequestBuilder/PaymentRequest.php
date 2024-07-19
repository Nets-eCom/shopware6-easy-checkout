<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder;

use NexiNets\CheckoutApi\Model\Request\Payment;
use NexiNets\CheckoutApi\Model\Request\Payment\IntegrationTypeEnum;
use NexiNets\CheckoutApi\Model\Request\Payment\Order;
use NexiNets\RequestBuilder\PaymentRequest\CheckoutBuilderFactory;
use NexiNets\RequestBuilder\PaymentRequest\Items;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PaymentRequest
{
    public function __construct(
        private readonly CheckoutBuilderFactory $checkoutBuilderFactory,
        private readonly Items $items
    ) {
    }

    public function build(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext,
        IntegrationTypeEnum $integrationType,
    ): Payment {
        return new Payment(
            new Order(
                $this->items->create($transaction->getOrder(), $salesChannelContext),
                $salesChannelContext->getCurrency()->getIsoCode(),
                (int) round($transaction->getOrderTransaction()->getAmount()->getTotalPrice() * 100) // TODO: use helper instead
            ),
            $this
                ->checkoutBuilderFactory
                ->build($integrationType)
                ->create(
                    $transaction,
                    $salesChannelContext
                )
        );
    }
}
