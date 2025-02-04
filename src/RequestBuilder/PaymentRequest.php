<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder;

use Nexi\Checkout\RequestBuilder\Helper\FormatHelper;
use Nexi\Checkout\RequestBuilder\PaymentRequest\CheckoutBuilderFactory;
use Nexi\Checkout\RequestBuilder\PaymentRequest\ItemsBuilder;
use Nexi\Checkout\RequestBuilder\PaymentRequest\NotificationBuilder;
use NexiCheckout\Model\Request\Payment;
use NexiCheckout\Model\Request\Payment\IntegrationTypeEnum;
use NexiCheckout\Model\Request\Payment\Order;
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
        $orderEntity = $transaction->getOrder();

        return new Payment(
            new Order(
                $this->itemsBuilder->create($orderEntity),
                $orderEntity->getCurrency()->getIsoCode(),
                $this->formatHelper->priceToInt($transaction->getAmount()->getTotalPrice()),
                $orderEntity->getOrderNumber()
            ),
            $this
                ->checkoutBuilderFactory
                ->build($integrationType)
                ->create(
                    $orderEntity,
                    $returnUrl,
                    $salesChannelId
                ),
            $this
                ->notificationBuilder
                ->create($salesChannelId)
        );
    }
}
