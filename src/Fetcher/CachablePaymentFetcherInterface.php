<?php declare(strict_types=1);

namespace Nexi\Checkout\Fetcher;

use NexiCheckout\Model\Result\RetrievePayment\Payment;

interface CachablePaymentFetcherInterface
{
    public function getCachedPayment(string $salesChannelId, string $paymentId): Payment;

    public function removeCache(string $paymentId): void;
}
