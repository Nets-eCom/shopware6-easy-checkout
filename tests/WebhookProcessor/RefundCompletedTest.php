<?php

declare(strict_types=1);

namespace NexiNets\Tests\WebhookProcessor;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\CheckoutApi\Model\Result\RetrievePaymentResult;
use NexiNets\CheckoutApi\Model\Webhook\Data\Amount;
use NexiNets\CheckoutApi\Model\Webhook\Data\RefundCompletedData;
use NexiNets\CheckoutApi\Model\Webhook\EventNameEnum;
use NexiNets\CheckoutApi\Model\Webhook\RefundCompleted as RefundCompletedModel;
use NexiNets\CheckoutApi\Model\Webhook\WebhookInterface;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\WebhookProcessor\Processor\RefundCompleted;
use NexiNets\WebhookProcessor\WebhookProcessorException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineException;
use Shopware\Core\Test\Generator;

final class RefundCompletedTest extends TestCase
{
    public function testItProcessFullRefundEvent(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeast(2))->method('info')->with($this->isType('string'));
        $paymentApiFactory = $this->createPaymentApiFactory(
            $this->createPaymentApi($this->createPayment(true))
        );
        $salesChannelContext = Generator::createSalesChannelContext();

        $orderTransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $orderTransactionStateHandler
            ->expects($this->once())
            ->method('refund')
            ->with(
                'foo',
                $salesChannelContext->getContext()
            );

        $sut = new RefundCompleted(
            $this->createTransactionRepository(OrderTransactionStates::STATE_PAID, 'foo'),
            $orderTransactionStateHandler,
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class),
            $logger
        );

        $sut->process(
            $this->createRefundCompletedWebhookEvent('foo'),
            $salesChannelContext
        );
    }

    public function testItProcessesPartialRefundEvent(): void
    {
        $transactionRepository = $this->createTransactionRepository(
            OrderTransactionStates::STATE_PARTIALLY_PAID,
            'foo'
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeast(2))->method('info')->with($this->isType('string'));

        $webhook = $this->createRefundCompletedWebhookEvent('foo');
        $payment = $this->createPayment(false);
        $paymentApi = $this->createPaymentApi($payment);
        $paymentApiFactory = $this->createPaymentApiFactory($paymentApi);
        $salesChannelContext = Generator::createSalesChannelContext();

        $orderTransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $orderTransactionStateHandler
            ->expects($this->once())
            ->method('refundPartially')
            ->with(
                'foo',
                $salesChannelContext->getContext()
            );

        $sut = new RefundCompleted(
            $transactionRepository,
            $orderTransactionStateHandler,
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class),
            $logger
        );

        $sut->process($webhook, $salesChannelContext);
    }

    public function testItThrowsExceptionWhenNoTransactionsFound(): void
    {
        $this->expectException(WebhookProcessorException::class);

        $searchResult = $this->createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new OrderTransactionCollection());

        $transactionRepository = $this->createStub(EntityRepository::class);
        $transactionRepository->method('search')->willReturn($searchResult);

        $refundCompletedData = $this->createStub(RefundCompletedData::class);
        $refundCompletedData->method('getPaymentId')->willReturn('123');

        $refundCompletedModel = $this->createStub(RefundCompletedModel::class);
        $refundCompletedModel->method('getData')->willReturn($refundCompletedData);

        $sut = new RefundCompleted(
            $transactionRepository,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createStub(PaymentApiFactory::class),
            $this->createStub(ConfigurationProvider::class),
            $this->createMock(LoggerInterface::class)
        );

        $sut->process($refundCompletedModel, Generator::createSalesChannelContext());
    }

    public function testItThrowsExceptionOnProcessInvalidDataType(): void
    {
        $this->expectException(WebhookProcessorException::class);

        $wrongModel = $this->createMock(WebhookInterface::class);

        $sut = new RefundCompleted(
            $this->createStub(EntityRepository::class),
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createStub(PaymentApiFactory::class),
            $this->createStub(ConfigurationProvider::class),
            $this->createMock(LoggerInterface::class)
        );

        $sut->process($wrongModel, Generator::createSalesChannelContext());
    }

    public function testItLogsStateMachineException(): void
    {
        $this->expectException(StateMachineException::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with($this->isType('string'));
        $paymentApiFactory = $this->createPaymentApiFactory(
            $this->createPaymentApi($this->createPayment(true))
        );
        $salesChannelContext = Generator::createSalesChannelContext();

        $orderTransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $orderTransactionStateHandler
            ->expects($this->once())
            ->method('refund')
            ->willThrowException(
                StateMachineException::illegalStateTransition(
                    OrderTransactionStates::STATE_OPEN,
                    OrderTransactionStates::STATE_REFUNDED,
                    [OrderTransactionStates::STATE_PAID]
                )
            );

        $logger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringStartsWith('payment.charge.created.v2 failed:'));

        $sut = new RefundCompleted(
            $this->createTransactionRepository(OrderTransactionStates::STATE_OPEN, 'foo'),
            $orderTransactionStateHandler,
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class),
            $logger
        );

        $sut->process(
            $this->createRefundCompletedWebhookEvent('foo'),
            $salesChannelContext
        );
    }

    private function createStateMachineState(string $state): StateMachineStateEntity
    {
        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setTechnicalName($state);

        return $stateMachineState;
    }

    private function createTransaction(string $state, string $id): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setStateMachineState($this->createStateMachineState($state));
        $transaction->setId($id);

        return $transaction;
    }

    /**
     * @return EntityRepository<OrderTransactionCollection>
     */
    private function createTransactionRepository(string $transactionTechnicalName, string $paymentId): EntityRepository
    {
        $transactionRepository = $this->createStub(EntityRepository::class);
        $transactionRepository
            ->method('search')
            ->willReturn(
                $this->createSearchResult(
                    $this->createTransaction($transactionTechnicalName, $paymentId)
                )
            );

        return $transactionRepository;
    }

    /**
     * @return EntitySearchResult<OrderTransactionCollection>
     */
    private function createSearchResult(OrderTransactionEntity $transaction): EntitySearchResult
    {
        $searchResult = $this->createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new OrderTransactionCollection([$transaction]));

        return $searchResult;
    }

    private function createRefundCompletedWebhookEvent(string $paymentId): RefundCompletedModel
    {
        return new RefundCompletedModel(
            'model_id',
            new \DateTime(),
            100000,
            EventNameEnum::PAYMENT_REFUND_COMPLETED,
            new RefundCompletedData(
                $paymentId,
                'refund_id',
                new Amount(100, 'EUR'),
            )
        );
    }

    private function createPayment(bool $isFullyRefunded): Payment
    {
        $payment = $this->createStub(Payment::class);
        $payment->method('isFullyRefunded')->willReturn($isFullyRefunded);

        return $payment;
    }

    private function createPaymentApi(Payment $payment): PaymentApi
    {
        $paymentApi = $this->createStub(PaymentApi::class);
        $paymentApi->method('retrievePayment')->willReturn(new RetrievePaymentResult($payment));

        return $paymentApi;
    }

    private function createPaymentApiFactory(PaymentApi $paymentApi): PaymentApiFactory
    {
        $paymentApiFactory = $this->createMock(PaymentApiFactory::class);
        $paymentApiFactory->method('create')->willReturn($paymentApi);

        return $paymentApiFactory;
    }
}
