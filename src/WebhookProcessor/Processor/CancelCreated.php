<?php declare(strict_types=1);

namespace NexiNets\WebhookProcessor\Processor;

use NexiNets\CheckoutApi\Model\Webhook\CancelCreated as CancelCreatedModel;
use NexiNets\CheckoutApi\Model\Webhook\EventNameEnum;
use NexiNets\CheckoutApi\Model\Webhook\WebhookInterface as WebhookModelInterface;
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

final readonly class CancelCreated implements WebhookProcessorInterface
{
    use StateMachineExceptionLogTrait;

    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionEntityRepository
     */
    public function __construct(
        private EntityRepository $orderTransactionEntityRepository,
        private OrderTransactionStateHandler $orderTransactionStateHandler,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws WebhookProcessorException
     */
    public function process(WebhookModelInterface $webhook, SalesChannelContext $salesChannelContext): void
    {
        if (!$webhook instanceof CancelCreatedModel) {
            throw new WebhookProcessorException('Invalid Data type');
        }

        $data = $webhook->getData();
        $paymentId = $data->getPaymentId();

        $this->logger->info('payment.cancel.created started', [
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
            $this->logger->info('payment.cancel.created order transaction already cancelled', [
                'paymentId' => $paymentId,
            ]);

            return;
        }

        try {
            $this->orderTransactionStateHandler->cancel($transactionId, $context);
        } catch (StateMachineException $stateMachineException) {
            $this->logStateMachineException($stateMachineException, $paymentId);

            throw $stateMachineException;
        }

        $this->logger->info('payment.cancel.created finished', [
            'paymentId' => $paymentId,
        ]);
    }

    public function getEvent(): EventNameEnum
    {
        return EventNameEnum::PAYMENT_CANCEL_CREATED;
    }
}
