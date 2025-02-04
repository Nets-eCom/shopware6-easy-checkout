<?php declare(strict_types=1);

namespace Nexi\Checkout\Fetcher;

use NexiCheckout\Model\Result\RetrievePayment\Payment;

interface PaymentFetcherInterface
{
    public function fetchPayment(string $salesChannelId, string $paymentId): Payment;
}
