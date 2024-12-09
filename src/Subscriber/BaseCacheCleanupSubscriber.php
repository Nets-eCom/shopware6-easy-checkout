<?php

declare(strict_types=1);

namespace NexiNets\Subscriber;

use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\Fetcher\CachablePaymentFetcherInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class BaseCacheCleanupSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly CachablePaymentFetcherInterface $paymentFetcher)
    {
    }

    protected function cleanCache(OrderTransactionEntity $transaction): void
    {
        $this->paymentFetcher->removeCache(
            $transaction->getCustomFieldsValue(
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID
            )
        );
    }
}
