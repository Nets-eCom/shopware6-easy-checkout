<?php declare(strict_types=1);

namespace NexiNets\Core\Content\Flow\Dispatching\Action;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\RequestBuilder\ChargeRequest;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\OrderAware;

class ChargeAction extends FlowAction
{
    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly ChargeRequest $chargeRequest,
        private readonly PaymentApiFactory $paymentApiFactory,
    ) {
    }

    public static function getName(): string
    {
        return 'action.nexinets.charge';
    }

    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(OrderAware::ORDER)) {
            return;
        }

        /** @var OrderEntity $order */
        $order = $flow->getData(OrderAware::ORDER);

        if ($this->configurationProvider->isAutoCharge($order->getSalesChannelId())) {
            return;
        }

        $transactions = $order->getTransactions();

        if ($transactions === null) {
            return;
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
            if ($payment->getStatus() !== PaymentStatusEnum::RESERVED) { // we only allow to charge full amount
                continue;
            }

            $charge = $this->chargeRequest->buildFullCharge($transaction);
            $paymentApi->charge($paymentId, $charge);
        }
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId),
        );
    }
}
