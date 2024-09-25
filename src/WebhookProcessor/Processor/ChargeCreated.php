<?php

declare(strict_types=1);

namespace NexiNets\WebhookProcessor\Processor;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\CheckoutApi\Model\Webhook\ChargeCreated as ChargeCreatedModel;
use NexiNets\CheckoutApi\Model\Webhook\EventNameEnum;
use NexiNets\CheckoutApi\Model\Webhook\WebhookInterface as WebhookModelInterface;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\WebhookProcessor\WebhookProcessorException;
use NexiNets\WebhookProcessor\WebhookProcessorInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineException;

final readonly class ChargeCreated implements WebhookProcessorInterface
{
    use StateMachineExceptionLogTrait;

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
        if (!$webhook instanceof ChargeCreatedModel) {
            throw new WebhookProcessorException('Invalid Data type');
        }

        $data = $webhook->getData();
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
            try {
                $this->orderTransactionStateHandler->payPartially($transactionId, $context);
            } catch (StateMachineException $stateMachineException) {
                $this->logStateMachineException($stateMachineException, $paymentId);

                throw $stateMachineException;
            }

            $this->logger->info('payment.charge.created.v2 finished', [
                'paymentId' => $paymentId,
            ]);

            return;
        }

        // Transaction marked as paid_partially has to be reopened before transition to paid
        if ($transactionState === OrderTransactionStates::STATE_PARTIALLY_PAID) {
            try {
                $this->orderTransactionStateHandler->reopen($transactionId, $context);
            } catch (StateMachineException $stateMachineException) {
                $this->logStateMachineException($stateMachineException, $paymentId);

                throw $stateMachineException;
            }
        }

        try {
            $this->orderTransactionStateHandler->paid($transactionId, $context);
        } catch (StateMachineException $stateMachineException) {
            $this->logStateMachineException($stateMachineException, $paymentId);

            throw $stateMachineException;
        }

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
