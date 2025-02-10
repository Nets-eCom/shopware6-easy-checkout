<?php declare(strict_types=1);

namespace Nexi\Checkout\WebhookProcessor\Processor;

use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Fetcher\CachablePaymentFetcherInterface;
use Nexi\Checkout\WebhookProcessor\WebhookProcessorException;
use Nexi\Checkout\WebhookProcessor\WebhookProcessorInterface;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiCheckout\Model\Webhook\EventNameEnum;
use NexiCheckout\Model\Webhook\WebhookInterface;
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
        $data = $webhook->getData();
        $paymentId = $data->getPaymentId();
        $event = $webhook->getEvent();

        $this->logProcessMessage($event, 'started', $paymentId);

        $context = $salesChannelContext->getContext();

        $criteria = (new Criteria())
            ->addAssociation('stateMachineState')
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

        $transaction = $transactions->first();
        $transactionId = $transaction->getId();

        if (!$this->isPaymentFullyRefunded($paymentId, $salesChannelContext->getSalesChannelId())) {
            try {
                $this->orderTransactionStateHandler->refundPartially($transactionId, $context);
            } catch (StateMachineException $stateMachineException) {
                $this->logStateMachineException($stateMachineException, $event, $paymentId);

                throw $stateMachineException;
            }

            $this->logProcessMessage($event, 'finished', $paymentId);

            return;
        }

        try {
            $this->orderTransactionStateHandler->refund($transactionId, $context);
        } catch (StateMachineException $stateMachineException) {
            $this->logStateMachineException($stateMachineException, $event, $paymentId);

            throw $stateMachineException;
        }

        $this->logProcessMessage($event, 'finished', $paymentId);
    }

    public function supports(WebhookInterface $webhook): bool
    {
        return $webhook->getEvent() === EventNameEnum::PAYMENT_REFUND_COMPLETED;
    }

    private function isPaymentFullyRefunded(string $paymentId, string $salesChannelId): bool
    {
        return $this->getPayment($salesChannelId, $paymentId)->getStatus() === PaymentStatusEnum::REFUNDED;
    }

    private function getPayment(string $salesChannelId, string $paymentId): Payment
    {
        return $this->paymentFetcher->getCachedPayment($salesChannelId, $paymentId);
    }
}
