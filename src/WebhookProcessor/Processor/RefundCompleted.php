<?php declare(strict_types=1);

namespace NexiNets\WebhookProcessor\Processor;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\CheckoutApi\Model\Webhook\EventNameEnum;
use NexiNets\CheckoutApi\Model\Webhook\WebhookInterface;
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
    use ProcessorLogTrait;

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
    public function process(WebhookInterface $webhook, SalesChannelContext $salesChannelContext): void
    {
        $data = $webhook->getData();
        $paymentId = $data->getPaymentId();
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
}
