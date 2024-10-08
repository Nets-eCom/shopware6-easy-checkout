<?php declare(strict_types=1);

namespace NexiNets\Fetcher;

use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;

interface CachablePaymentFetcherInterface
{
    public function getCachedPayment(string $salesChannelId, string $paymentId): Payment;

    public function updateCache(string $salesChannelId, string $paymentId): void;
}
