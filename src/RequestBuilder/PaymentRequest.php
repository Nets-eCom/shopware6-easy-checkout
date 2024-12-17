<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder;

use NexiNets\CheckoutApi\Model\Request\Payment;
use NexiNets\CheckoutApi\Model\Request\Payment\IntegrationTypeEnum;
use NexiNets\CheckoutApi\Model\Request\Payment\Order;
use NexiNets\RequestBuilder\Helper\FormatHelper;
use NexiNets\RequestBuilder\PaymentRequest\CheckoutBuilderFactory;
use NexiNets\RequestBuilder\PaymentRequest\ItemsBuilder;
use NexiNets\RequestBuilder\PaymentRequest\NotificationBuilder;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class PaymentRequest
{
    public function __construct(
        private readonly CheckoutBuilderFactory $checkoutBuilderFactory,
        private readonly ItemsBuilder $itemsBuilder,
        private readonly NotificationBuilder $notificationBuilder,
        private readonly FormatHelper $formatHelper,
    ) {
    }

    public function build(
        OrderTransactionEntity $transaction,
        string $salesChannelId,
        string $returnUrl,
        IntegrationTypeEnum $integrationType,
    ): Payment {
        return new Payment(
            new Order(
                $this->itemsBuilder->create($transaction->getOrder()),
                $transaction->getOrder()->getCurrency()->getIsoCode(),
                $this->formatHelper->priceToInt($transaction->getAmount()->getTotalPrice()),
                $transaction->getOrder()->getOrderNumber()
            ),
            $this
                ->checkoutBuilderFactory
                ->build($integrationType)
                ->create(
                    $transaction,
                    $returnUrl,
                    $salesChannelId
                ),
            $this
                ->notificationBuilder
                ->create($salesChannelId)
        );
    }
}
