<?php

declare(strict_types=1);

namespace NexiNets\WebhookProcessor\Processor;

use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiNets\CheckoutApi\Model\Webhook\EventNameEnum;
use NexiNets\CheckoutApi\Model\Webhook\WebhookInterface;
use NexiNets\Fetcher\CachablePaymentFetcherInterface;
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
    use ProcessorLogTrait;

    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionEntityRepository
     */
    public function __construct(
        private EntityRepository $orderTransactionEntityRepository,
        private OrderTransactionStateHandler $orderTransactionStateHandler,
        private CachablePaymentFetcherInterface $paymentFetcher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws WebhookProcessorException
     */
    public function process(WebhookInterface $webhook, SalesChannelContext $salesChannelContext): void
    {
        $paymentId = $webhook->getData()->getPaymentId();

        $event = $webhook->getEvent();
        $this->logProcessMessage($event, 'started', $paymentId);

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
            $this->logProcessMessage($event, 'cancelled', $paymentId);

            return;
        }

        if (!$this->isPaymentFullyCharged($paymentId, $salesChannelContext->getSalesChannelId())) {
            try {
                $this->orderTransactionStateHandler->payPartially($transactionId, $context);
            } catch (StateMachineException $stateMachineException) {
                $this->logStateMachineException($stateMachineException, $event, $paymentId);

                throw $stateMachineException;
            }

            $this->logProcessMessage($event, 'finished', $paymentId);

            return;
        }

        // Transaction marked as paid_partially has to be reopened before transition to paid
        if ($transactionState === OrderTransactionStates::STATE_PARTIALLY_PAID) {
            try {
                $this->orderTransactionStateHandler->reopen($transactionId, $context);
            } catch (StateMachineException $stateMachineException) {
                $this->logStateMachineException($stateMachineException, $event, $paymentId);

                throw $stateMachineException;
            }
        }

        try {
            $this->orderTransactionStateHandler->paid($transactionId, $context);
        } catch (StateMachineException $stateMachineException) {
            $this->logStateMachineException($stateMachineException, $event, $paymentId);

            throw $stateMachineException;
        }

        $this->logProcessMessage($event, 'finished', $paymentId);
    }

    public function supports(WebhookInterface $webhook): bool
    {
        return $webhook->getEvent() === EventNameEnum::PAYMENT_CHARGE_CREATED;
    }

    private function isPaymentFullyCharged(string $paymentId, string $salesChannelId): bool
    {
        return $this->getPayment($salesChannelId, $paymentId)->getStatus() === PaymentStatusEnum::CHARGED;
    }

    private function getPayment(string $salesChannelId, string $paymentId): Payment
    {
        return $this->paymentFetcher->getCachedPayment($salesChannelId, $paymentId);
    }
}
