<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder;

use Nexi\Checkout\RequestBuilder\Helper\FormatHelper;
use Nexi\Checkout\RequestBuilder\PaymentRequest\CheckoutBuilderFactory;
use Nexi\Checkout\RequestBuilder\PaymentRequest\ItemsBuilder;
use Nexi\Checkout\RequestBuilder\PaymentRequest\NotificationBuilder;
use NexiCheckout\Model\Request\Item;
use NexiCheckout\Model\Request\Payment;
use NexiCheckout\Model\Request\Payment\EmbeddedCheckout;
use NexiCheckout\Model\Request\Payment\IntegrationTypeEnum;
use NexiCheckout\Model\Request\Shared\Order;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PaymentRequest
{
    public function __construct(
        private readonly CheckoutBuilderFactory $checkoutBuilderFactory,
        private readonly ItemsBuilder $itemsBuilder,
        private readonly NotificationBuilder $notificationBuilder,
        private readonly FormatHelper $formatHelper,
    ) {
    }

    public function buildHosted(
        OrderTransactionEntity $transaction,
        string $salesChannelId,
        string $returnUrl
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
                ->build(IntegrationTypeEnum::HostedPaymentPage)
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

    public function buildEmbedded(
        Cart $cart,
        SalesChannelContext $salesChannelContext,
        string $checkoutUrl
    ): Payment {
        // @TODO: Implement

        $price = $this->formatHelper->priceToInt($cart->getPrice()->getTotalPrice());

        return new Payment(
            order: new Order(
                [new Item('Fake item', 1, 'pcs', $price, $price, $price, 'Fake item')],
                $salesChannelContext->getCurrency()->getIsoCode(),
                $price,
                $cart->getToken()
            ),
            checkout: new EmbeddedCheckout($checkoutUrl, 'https://example.com'),
            notification: $this
                ->notificationBuilder
                ->create($salesChannelContext->getSalesChannelId()),
            myReference: $cart->getToken(),
        );
    }
}
