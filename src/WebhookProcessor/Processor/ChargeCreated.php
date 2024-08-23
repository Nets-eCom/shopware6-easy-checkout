<?php

declare(strict_types=1);

namespace NexiNets\WebhookProcessor\Processor;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\CheckoutApi\Model\Webhook\Data\ChargeCreated as DataChargeCreated;
use NexiNets\CheckoutApi\Model\Webhook\EventNameEnum;
use NexiNets\CheckoutApi\Model\Webhook\Webhook;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\WebhookProcessor\WebhookInterface;
use NexiNets\WebhookProcessor\WebhookProcessorException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class ChargeCreated implements WebhookInterface
{
    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionEntityRepository
     */
    public function __construct(
        private EntityRepository $orderTransactionEntityRepository,
        private LoggerInterface $logger,
        private OrderTransactionStateHandler $orderTransactionStateHandler,
        private PaymentApiFactory $paymentApiFactory,
        private ConfigurationProvider $configurationProvider
    ) {
    }

    /**
     * @throws WebhookProcessorException
     */
    public function process(Webhook $webhook, SalesChannelContext $salesChannelContext): void
    {
        $data = $webhook->getData();

        if (!$data instanceof DataChargeCreated) {
            throw new WebhookProcessorException('Invalid Data type');
        }

        $paymentId = $data->getPaymentId();

        $this->logger->info('payment.charge.created.v2 started', [
            'paymentId' => $paymentId,
        ]);

        $context = $salesChannelContext->getContext();

        $criteria = (new Criteria())
            ->addAssociation('stateMachineState')
            ->addFilter(new EqualsFilter('customFields.nexi_nets_payment_id', $paymentId));

        /** @var OrderTransactionCollection $transactions */
        $transactions = $this->orderTransactionEntityRepository->search($criteria, $context)->getEntities();

        if (!$transactions->count() > 0) {
            throw new WebhookProcessorException(
                \sprintf('No transactions found for a given payment id: %s', $paymentId)
            );
        }

        $transaction = $transactions->first();
        $transactionId = $transaction->getId();
        $transactionState = $transaction->getStateMachineState()->getTechnicalName();

        if ($transactionState === OrderTransactionStates::STATE_CANCELLED) {
            $this->logger->info('payment.charge.created.v2 order transaction cancelled', [
                'paymentId' => $paymentId,
            ]);

            return;
        }

        if (!$this->isPaymentFullyCharged($paymentId, $salesChannelContext->getSalesChannelId())) {
            $this->orderTransactionStateHandler->payPartially($transactionId, $context);

            $this->logger->info('payment.charge.created.v2 finished', [
                'paymentId' => $paymentId,
            ]);

            return;
        }

        // Transaction marked as paid_partially has to be reopened before transition to paid
        if ($transactionState === OrderTransactionStates::STATE_PARTIALLY_PAID) {
            $this->orderTransactionStateHandler->reopen($transactionId, $context);
        }

        $this->orderTransactionStateHandler->paid($transactionId, $context);

        $this->logger->info('payment.charge.created.v2 finished', [
            'paymentId' => $paymentId,
        ]);
    }

    public function getEvent(): EventNameEnum
    {
        return EventNameEnum::PAYMENT_CHARGE_CREATED;
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId),
        );
    }

    private function isPaymentFullyCharged(string $paymentId, string $salesChannelId): bool
    {
        return $this->getPayment($salesChannelId, $paymentId)->isFullyCharged();
    }

    private function getPayment(string $salesChannelId, string $paymentId): Payment
    {
        return $this
            ->createPaymentApi($salesChannelId)
            ->retrievePayment($paymentId)
            ->getPayment();
    }
}
