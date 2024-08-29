<?php

declare(strict_types=1);

namespace NexiNets\Tests\WebhookProcessor;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\CheckoutApi\Model\Result\RetrievePaymentResult;
use NexiNets\CheckoutApi\Model\Webhook\Data\ChargeCreated as DataChargeCreated;
use NexiNets\CheckoutApi\Model\Webhook\Webhook;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\WebhookProcessor\Processor\ChargeCreated;
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
use Shopware\Core\Test\Generator;

final class ChargeCreatedTest extends TestCase
{
    public function testItProcessFullChargedEvent(): void
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
            ->method('paid')
            ->with(
                'foo',
                $salesChannelContext->getContext()
            );

        $sut = new ChargeCreated(
            $this->createTransactionRepository(OrderTransactionStates::STATE_OPEN, 'foo'),
            $logger,
            $orderTransactionStateHandler,
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class)
        );

        $sut->process(
            $this->createChargeCreatedWebhookEvent($this->createDataChargeCreated('foo')),
            $salesChannelContext
        );
    }

    public function testItProcessesPartialChargedEvent(): void
    {
        $transactionRepository = $this->createTransactionRepository(
            OrderTransactionStates::STATE_PARTIALLY_PAID,
            'foo'
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeast(2))->method('info')->with($this->isType('string'));

        $webhook = $this->createChargeCreatedWebhookEvent($this->createDataChargeCreated('foo'));
        $payment = $this->createPayment(false);
        $paymentApi = $this->createPaymentApi($payment);
        $paymentApiFactory = $this->createPaymentApiFactory($paymentApi);
        $salesChannelContext = Generator::createSalesChannelContext();

        $orderTransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $orderTransactionStateHandler
            ->expects($this->once())
            ->method('payPartially')
            ->with(
                'foo',
                $salesChannelContext->getContext()
            );

        $sut = new ChargeCreated(
            $transactionRepository,
            $logger,
            $orderTransactionStateHandler,
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class)
        );

        $sut->process($webhook, $salesChannelContext);
    }

    public function testItProcessesFullChargeOnPreviousPartialChargeEvent(): void
    {
        $transactionRepository = $this->createTransactionRepository(
            OrderTransactionStates::STATE_PARTIALLY_PAID,
            'foo'
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeast(2))->method('info')->with($this->isType('string'));
        $webhook = $this->createChargeCreatedWebhookEvent($this->createDataChargeCreated('foo'));
        $paymentApi = $this->createPaymentApi($this->createPayment(true));
        $paymentApiFactory = $this->createPaymentApiFactory($paymentApi);
        $salesChannelContext = Generator::createSalesChannelContext();

        $orderTransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $orderTransactionStateHandler
            ->expects($this->once())
            ->method('reopen')
            ->with(
                'foo',
                $salesChannelContext->getContext()
            );

        $orderTransactionStateHandler
            ->expects($this->once())
            ->method('paid')
            ->with(
                'foo',
                $salesChannelContext->getContext()
            );

        $sut = new ChargeCreated(
            $transactionRepository,
            $logger,
            $orderTransactionStateHandler,
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class)
        );

        $sut->process($webhook, $salesChannelContext);
    }

    public function testItStopsProcessOnCancelledTransaction(): void
    {
        $transactionRepository = $this->createTransactionRepository(OrderTransactionStates::STATE_CANCELLED, '123');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeast(2))->method('info')->with($this->isType('string'));

        $webhook = $this->createChargeCreatedWebhookEvent($this->createDataChargeCreated('123'));
        $paymentApiFactory = $this->createPaymentApiFactory($this->createPaymentApi($this->createPayment(false)));
        $salesChannelContext = Generator::createSalesChannelContext();

        $sut = new ChargeCreated(
            $transactionRepository,
            $logger,
            $this->createMock(OrderTransactionStateHandler::class),
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class)
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

        $dataChargeCreated = $this->createStub(DataChargeCreated::class);
        $dataChargeCreated->method('getPaymentId')->willReturn('123');

        $webhook = $this->createStub(Webhook::class);
        $webhook->method('getData')->willReturn($dataChargeCreated);

        $sut = new ChargeCreated(
            $transactionRepository,
            $this->createMock(LoggerInterface::class),
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createStub(PaymentApiFactory::class),
            $this->createStub(ConfigurationProvider::class)
        );

        $sut->process($webhook, Generator::createSalesChannelContext());
    }

    public function testItThrowsExceptionOnProcessInvalidDataType(): void
    {
        $this->expectException(WebhookProcessorException::class);

        $webhook = $this->createMock(Webhook::class);
        $webhook->method('getData')->willReturn(null);

        $sut = new ChargeCreated(
            $this->createStub(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createStub(PaymentApiFactory::class),
            $this->createStub(ConfigurationProvider::class)
        );

        $sut->process($webhook, Generator::createSalesChannelContext());
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

    private function createDataChargeCreated(string $paymentId): DataChargeCreated
    {
        $dataChargeCreated = $this->createMock(DataChargeCreated::class);
        $dataChargeCreated->method('getPaymentId')->willReturn($paymentId);

        return $dataChargeCreated;
    }

    private function createChargeCreatedWebhookEvent(DataChargeCreated $dataChargeCreated): Webhook
    {
        $webhook = $this->createMock(Webhook::class);
        $webhook->method('getData')->willReturn($dataChargeCreated);

        return $webhook;
    }

    private function createPayment(bool $isFullyCharged): Payment
    {
        $payment = $this->createStub(Payment::class);
        $payment->method('isFullyCharged')->willReturn($isFullyCharged);

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
