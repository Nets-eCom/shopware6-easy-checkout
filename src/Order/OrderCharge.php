<?php

declare(strict_types=1);

namespace NexiNets\Order;

use NexiNets\Administration\Model\ChargeData;
use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\RequestBuilder\ChargeRequest;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class OrderCharge
{
    public function __construct(
        private readonly PaymentApiFactory $apiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly ChargeRequest $chargeRequest
    ) {
    }

    /**
     * @throws PaymentApiException
     * @throws \LogicException
     */
    public function fullCharge(OrderEntity $order): void
    {
        if ($this->configurationProvider->isAutoCharge($order->getSalesChannelId())) {
            return;
        }

        $transactions = $order->getTransactions();

        if (!$transactions instanceof OrderTransactionCollection) {
            throw new \LogicException('No order transactions found');
        }

        /** @var OrderTransactionEntity $transaction */
        foreach ($transactions as $transaction) {
            $paymentId = $transaction->getCustomFieldsValue(
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID
            );

            if ($paymentId === null) {
                continue;
            }

            $paymentApi = $this->createPaymentApi($order->getSalesChannelId());
            $payment = $paymentApi->retrievePayment($paymentId)->getPayment();

            if ($payment->getStatus() !== PaymentStatusEnum::RESERVED) {
                continue;
            }

            $paymentApi->charge($paymentId, $this->chargeRequest->buildFullCharge($transaction));
        }
    }

    public function partialCharge(OrderEntity $order, ChargeData $chargeData): void
    {
        // TODO: Implement
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->apiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId)
        );
    }
}
