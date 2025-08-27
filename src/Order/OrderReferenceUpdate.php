<?php declare(strict_types=1);

namespace Nexi\Checkout\Order;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Fetcher\CachablePaymentFetcherInterface;
use Nexi\Checkout\Order\Exception\OrderReferenceUpdateException;
use Nexi\Checkout\RequestBuilder\ReferenceInformationRequest;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class OrderReferenceUpdate
{
    public function __construct(
        private readonly CachablePaymentFetcherInterface $paymentFetcher,
        private readonly PaymentApiFactory $apiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly ReferenceInformationRequest $referenceInformationRequest,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws OrderReferenceUpdateException
     */
    public function updateReferenceForTransaction(OrderTransactionEntity $transaction): void
    {
        $order = $transaction->getOrder();
        $paymentApi = $this->createPaymentApi($order->getSalesChannelId());

        $paymentId = $transaction->getCustomFieldsValue(
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID
        );

        if ($paymentId === null) {
            return;
        }

        $payment = $this->paymentFetcher->getCachedPayment($order->getSalesChannelId(), $paymentId);

        $payload = $this->referenceInformationRequest->build($payment->getCheckout()->getUrl(), $transaction);

        $this->logger->info('Update reference information request', [
            'paymentId' => $paymentId,
        ]);

        try {
            $paymentApi->updateReferenceInformation(
                $paymentId,
                $payload
            );
        } catch (PaymentApiException $paymentApiException) {
            $this->logger->error('Update reference information request failed', [
                'paymentId' => $paymentId,
                'payload' => $payload,
                'exception' => $paymentApiException,
            ]);

            throw new OrderReferenceUpdateException($paymentId, previous: $paymentApiException);
        }

        $this->logger->info('Update reference information request success', [
            'paymentId' => $paymentId,
        ]);
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->apiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId)
        );
    }
}
