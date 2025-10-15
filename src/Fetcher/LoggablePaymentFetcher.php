<?php

declare(strict_types=1);

namespace Nexi\Checkout\Fetcher;

use NexiCheckout\Model\Result\RetrievePayment\Payment;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;

readonly class LoggablePaymentFetcher implements PaymentFetcherInterface, CachablePaymentFetcherInterface
{
    public function __construct(
        private PaymentFetcher $decorated,
        private LoggerInterface $logger,
        private SerializerInterface $serializer
    ) {
    }

    public function getCachedPayment(string $salesChannelId, string $paymentId): Payment
    {
        $this->logger->info(
            'Retrieve cached payment: id {paymentId}, channel {salesChannelId}.',
            [
                'salesChannelId' => $salesChannelId,
                'paymentId' => $paymentId,
            ]
        );

        $payment = $this->decorated->getCachedPayment($salesChannelId, $paymentId);

        $this->logger->info(
            'Retrieved cached payment.',
            [
                'payment' => $this->serializer->serialize($payment, 'json'),
            ]
        );

        return $payment;
    }

    public function removeCache(string $paymentId): void
    {
        $this->logger->info('Remove cached payment: id {paymentId}', [
            'paymentId' => $paymentId,
        ]);

        $this->decorated->removeCache($paymentId);

        $this->logger->info('Cached payment removed: id {paymentId}.', [
            'paymentId' => $paymentId,
        ]);
    }

    public function fetchPayment(string $salesChannelId, string $paymentId): Payment
    {
        $this->logger->info(
            'Fetch payment: id {paymentId}, channel {salesChannelId}.',
            [
                'salesChannelId' => $salesChannelId,
                'paymentId' => $paymentId,
            ]
        );

        $payment = $this->decorated->fetchPayment($salesChannelId, $paymentId);

        $this->logger->info(
            'Fetched payment.',
            [
                'payment' => $this->serializer->serialize($payment, 'json'),
            ]
        );

        return $payment;
    }
}
