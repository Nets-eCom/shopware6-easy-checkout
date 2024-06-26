<?php

declare(strict_types=1);

namespace Nets\Checkout\Service\DataReader;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class OrderDataReader
{
    private EntityRepository $orderRepository;

    public function __construct(
        EntityRepository $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
    }

    public function getOrderEntityById(Context $context, string $orderId): mixed
    {
        $criteria = new Criteria([
            $orderId,
        ]);
        $criteria->addAssociation('lineItems.payload')
            ->addAssociation('deliveries.shippingCosts')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('cartPrice.calculatedTaxes')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('currency')
            ->addAssociation('addresses.country')
            ->addAssociation('stateMachineState')
            ->addAssociation('transactions.stateMachineState');

        return $this->orderRepository->search($criteria, $context)->first();
    }
}
