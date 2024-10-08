<?php declare(strict_types=1);

namespace NexiNets\Fetcher;

use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;

interface PaymentFetcherInterface
{
    public function fetchPayment(string $salesChannelId, string $paymentId): Payment;
}
