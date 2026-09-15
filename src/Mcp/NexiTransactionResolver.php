<?php

declare(strict_types=1);

namespace Nexi\Checkout\Mcp;

use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

final class NexiTransactionResolver
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function resolve(
        string $orderId = '',
        string $orderNumber = '',
        string $paymentId = '',
    ): OrderTransactionEntity {
        $identifiers = array_filter([
            'orderId' => trim($orderId),
            'orderNumber' => trim($orderNumber),
            'paymentId' => trim($paymentId),
        ], fn (string $value): bool => $value !== '');

        if (\count($identifiers) !== 1) {
            throw new \InvalidArgumentException('Provide exactly one of orderId, orderNumber, or paymentId.');
        }

        $criteria = $this->createCriteria($identifiers);
        $criteria->addAssociation('transactions.order');
        $criteria->addAssociation('currency');

        $orders = $this->orderRepository->search($criteria, $this->contextProvider->getContext())->getEntities();
        if ($orders->count() === 0) {
            throw new \InvalidArgumentException('No Shopware order was found for the supplied identifier.');
        }

        if ($orders->count() > 1) {
            throw new \InvalidArgumentException('The supplied identifier matches multiple Shopware orders.');
        }

        $order = $orders->first();
        if (!$order instanceof OrderEntity) {
            throw new \InvalidArgumentException('No Shopware order was found for the supplied identifier.');
        }

        foreach ($order->getTransactions() ?? [] as $transaction) {
            $transactionPaymentId = $transaction->getCustomFieldsValue(
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID
            );

            if (!\is_string($transactionPaymentId) || $transactionPaymentId === '') {
                continue;
            }

            if (isset($identifiers['paymentId']) && $transactionPaymentId !== $identifiers['paymentId']) {
                continue;
            }

            return $transaction;
        }

        throw new \InvalidArgumentException('The order has no matching Nexi Checkout payment.');
    }

    /**
     * @param array<string, string> $identifiers
     */
    private function createCriteria(array $identifiers): Criteria
    {
        if (isset($identifiers['orderId'])) {
            return new Criteria([$identifiers['orderId']]);
        }

        $criteria = new Criteria();
        if (isset($identifiers['orderNumber'])) {
            return $criteria->addFilter(new EqualsFilter('orderNumber', $identifiers['orderNumber']));
        }

        return $criteria->addFilter(new EqualsFilter(
            'transactions.customFields.' . OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID,
            $identifiers['paymentId']
        ));
    }
}
