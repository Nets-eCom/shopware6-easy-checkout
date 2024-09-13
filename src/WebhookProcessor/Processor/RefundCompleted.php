<?php declare(strict_types=1);

namespace NexiNets\WebhookProcessor\Processor;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\CheckoutApi\Model\Webhook\EventNameEnum;
use NexiNets\CheckoutApi\Model\Webhook\RefundCompleted as RefundCompletedModel;
use NexiNets\CheckoutApi\Model\Webhook\WebhookInterface as WebhookModelInterface;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\WebhookProcessor\WebhookProcessorException;
use NexiNets\WebhookProcessor\WebhookProcessorInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineException;

final readonly class RefundCompleted implements WebhookProcessorInterface
{
    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionEntityRepository
     */
    public function __construct(
        private EntityRepository $orderTransactionEntityRepository,
        private OrderTransactionStateHandler $orderTransactionStateHandler,
        private PaymentApiFactory $paymentApiFactory,
        private ConfigurationProvider $configurationProvider,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws WebhookProcessorException
     */
    public function process(WebhookModelInterface $webhook, SalesChannelContext $salesChannelContext): void
    {
        if (!$webhook instanceof RefundCompletedModel) {
            throw new WebhookProcessorException('Invalid Data type');
        }

        $data = $webhook->getData();
        $paymentId = $data->getPaymentId();

        $this->logger->info('payment.refund.completed started', [
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

        if (!$this->isPaymentFullyRefunded($paymentId, $salesChannelContext->getSalesChannelId())) {
            try {
                $this->orderTransactionStateHandler->refundPartially($transactionId, $context);
            } catch (StateMachineException $stateMachineException) {
                $this->logStateMachineException($stateMachineException, $paymentId);

                throw $stateMachineException;
            }

            $this->logger->info('payment.refund.completed finished', [
                'paymentId' => $paymentId,
            ]);

            return;
        }

        try {
            $this->orderTransactionStateHandler->refund($transactionId, $context);
        } catch (StateMachineException $stateMachineException) {
            $this->logStateMachineException($stateMachineException, $paymentId);

            throw $stateMachineException;
        }

        $this->logger->info('payment.refund.completed finished', [
            'paymentId' => $paymentId,
        ]);
    }

    public function getEvent(): EventNameEnum
    {
        return EventNameEnum::PAYMENT_REFUND_COMPLETED;
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId),
        );
    }

    private function isPaymentFullyRefunded(string $paymentId, string $salesChannelId): bool
    {
        return $this->getPayment($salesChannelId, $paymentId)->isFullyRefunded();
    }

    private function getPayment(string $salesChannelId, string $paymentId): Payment
    {
        return $this
            ->createPaymentApi($salesChannelId)
            ->retrievePayment($paymentId)
            ->getPayment();
    }

    private function logStateMachineException(StateMachineException $stateMachineException, string $paymentId): void
    {
        $this->logger->error(
            'payment.charge.created.v2 failed: ' . $stateMachineException->getMessage(),
            [
                'paymentId' => $paymentId,
                'exception' => $stateMachineException,
            ]
        );
    }
}
