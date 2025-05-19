<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder;

use Nexi\Checkout\Helper\FormatHelper;
use Nexi\Checkout\RequestBuilder\PaymentRequest\CheckoutBuilder;
use Nexi\Checkout\RequestBuilder\PaymentRequest\ItemsBuilder;
use Nexi\Checkout\RequestBuilder\PaymentRequest\NotificationBuilder;
use NexiCheckout\Model\Request\Payment;
use NexiCheckout\Model\Request\Shared\Order;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PaymentRequest
{
    public function __construct(
        private readonly CheckoutBuilder $checkoutBuilder,
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
                $this->itemsBuilder->createFromOrder($orderEntity),
                $orderEntity->getCurrency()->getIsoCode(),
                $this->formatHelper->priceToInt($transaction->getAmount()->getTotalPrice()),
                $orderEntity->getOrderNumber()
            ),
            $this
                ->checkoutBuilder
                ->createHosted(
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
        SalesChannelContext $salesChannelContext
    ): Payment {
        return new Payment(
            order: new Order(
                $this->itemsBuilder->createFromCart($cart),
                $salesChannelContext->getCurrency()->getIsoCode(),
                $this->formatHelper->priceToInt($cart->getPrice()->getTotalPrice()),
                $cart->getToken()
            ),
            checkout: $this->checkoutBuilder->createEmbedded($salesChannelContext),
            notification: $this
                ->notificationBuilder
                ->create($salesChannelContext->getSalesChannelId()),
            myReference: $cart->getToken(),
        );
    }
}
