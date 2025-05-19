<?php

declare(strict_types=1);

namespace Nexi\Checkout\WebhookProcessor\Processor;

use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Handler\EmbeddedPayment;
use Nexi\Checkout\WebhookProcessor\WebhookProcessorException;
use Nexi\Checkout\WebhookProcessor\WebhookProcessorInterface;
use NexiCheckout\Model\Webhook\EventNameEnum;
use NexiCheckout\Model\Webhook\WebhookInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineException;

final readonly class ReservationCreated implements WebhookProcessorInterface
{
    use ProcessorLogTrait;

    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionEntityRepository
     */
    public function __construct(
        private EntityRepository $orderTransactionEntityRepository,
        private OrderTransactionStateHandler $orderTransactionStateHandler,
        private LoggerInterface $logger,
    ) {
    }

    public function process(WebhookInterface $webhook, SalesChannelContext $salesChannelContext): void
    {
        $paymentId = $webhook->getData()->getPaymentId();
        $context = $salesChannelContext->getContext();
        $event = $webhook->getEvent();

        $transaction = $this->fetchOrderTransaction($paymentId, $context);

        if ($transaction->getPaymentMethod()?->getHandlerIdentifier() !== EmbeddedPayment::class) {
            return;
        }

        $this->logProcessMessage($event, 'start', $paymentId);

        $transactionId = $transaction->getId();
        $transactionState = $transaction->getStateMachineState()->getTechnicalName();

        if ($transactionState !== OrderTransactionStates::STATE_IN_PROGRESS) {
            $this->logProcessMessage($event, \sprintf('order transaction in wrong state: %s', $transactionState), $paymentId);

            return;
        }

        try {
            $this->orderTransactionStateHandler->authorize($transactionId, $context);
        } catch (StateMachineException $stateMachineException) {
            $this->logStateMachineException($stateMachineException, $event, $paymentId);

            throw $stateMachineException;
        }

        $this->logProcessMessage($event, 'finished', $paymentId);
    }

    public function supports(WebhookInterface $webhook): bool
    {
        return $webhook->getEvent() === EventNameEnum::PAYMENT_RESERVATION_CREATED_V2;
    }

    private function fetchOrderTransaction(
        string $paymentId,
        Context $context
    ): OrderTransactionEntity {
        $criteria = (new Criteria())
            ->addAssociation('stateMachineState')
            ->addAssociation('paymentMethod')
            ->addFilter(
                new EqualsFilter(
                    OrderTransactionDictionary::CUSTOM_FIELDS_PREFIX . OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID,
                    $paymentId
                )
            );

        /** @var OrderTransactionCollection $transactions */
        $transactions = $this->orderTransactionEntityRepository->search($criteria, $context)->getEntities();

        if (!$transactions->count() > 0) {
            throw new WebhookProcessorException(
                \sprintf('No transactions found for a given payment id: %s', $paymentId)
            );
        }

        return $transactions->first();
    }
}
