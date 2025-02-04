<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\WebhookProcessor;

use Nexi\Checkout\Fetcher\CachablePaymentFetcherInterface;
use Nexi\Checkout\WebhookProcessor\Processor\ChargeCreated;
use Nexi\Checkout\WebhookProcessor\WebhookProcessorException;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiCheckout\Model\Webhook\ChargeCreated as ChargeCreatedModel;
use NexiCheckout\Model\Webhook\Data\ChargeCreatedData;
use NexiCheckout\Model\Webhook\EventNameEnum;
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
        $paymentFetcher = $this->createPaymentFetcher(
            $this->createPayment(true)
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
            $orderTransactionStateHandler,
            $paymentFetcher,
            $logger
        );

        $sut->process(
            $this->createChargeCreatedWebhookEvent($this->createChargeCreatedData('foo')),
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

        $webhook = $this->createChargeCreatedWebhookEvent($this->createChargeCreatedData('foo'));
        $payment = $this->createPayment(false);
        $paymentFetcher = $this->createPaymentFetcher($payment);
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
            $orderTransactionStateHandler,
            $paymentFetcher,
            $logger
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
        $webhook = $this->createChargeCreatedWebhookEvent($this->createChargeCreatedData('foo'));
        $paymentFetcher = $this->createPaymentFetcher($this->createPayment(true));
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
            $orderTransactionStateHandler,
            $paymentFetcher,
            $logger
        );

        $sut->process($webhook, $salesChannelContext);
    }

    public function testItStopsProcessOnCancelledTransaction(): void
    {
        $transactionRepository = $this->createTransactionRepository(OrderTransactionStates::STATE_CANCELLED, '123');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeast(2))->method('info')->with($this->isType('string'));

        $webhook = $this->createChargeCreatedWebhookEvent($this->createChargeCreatedData('123'));
        $paymentFetcher = $this->createPaymentFetcher($this->createPayment(false));
        $salesChannelContext = Generator::createSalesChannelContext();

        $sut = new ChargeCreated(
            $transactionRepository,
            $this->createMock(OrderTransactionStateHandler::class),
            $paymentFetcher,
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

        $chargeCreatedData = $this->createStub(ChargeCreatedData::class);
        $chargeCreatedData->method('getPaymentId')->willReturn('123');

        $chargeCreatedModel = $this->createStub(ChargeCreatedModel::class);
        $chargeCreatedModel->method('getData')->willReturn($chargeCreatedData);
        $chargeCreatedModel->method('getEvent')->willReturn(EventNameEnum::PAYMENT_CHARGE_CREATED);

        $sut = new ChargeCreated(
            $transactionRepository,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createStub(CachablePaymentFetcherInterface::class),
            $this->createMock(LoggerInterface::class)
        );

        $sut->process($chargeCreatedModel, Generator::createSalesChannelContext());
    }

    public function testItSupportsChargeCreatedWebhook(): void
    {
        $sut = new ChargeCreated(
            $this->createStub(EntityRepository::class),
            $this->createStub(OrderTransactionStateHandler::class),
            $this->createStub(CachablePaymentFetcherInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $webhook = $this->createStub(ChargeCreatedModel::class);
        $webhook->method('getEvent')->willReturn(EventNameEnum::PAYMENT_CHARGE_CREATED);

        $this->assertTrue($sut->supports($webhook));
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

    private function createChargeCreatedData(string $paymentId): ChargeCreatedData
    {
        $chargeCreatedData = $this->createMock(ChargeCreatedData::class);
        $chargeCreatedData->method('getPaymentId')->willReturn($paymentId);

        return $chargeCreatedData;
    }

    private function createChargeCreatedWebhookEvent(ChargeCreatedData $chargeCreatedData): ChargeCreatedModel
    {
        $webhook = $this->createMock(ChargeCreatedModel::class);
        $webhook->method('getData')->willReturn($chargeCreatedData);
        $webhook->method('getEvent')->willReturn(EventNameEnum::PAYMENT_CHARGE_CREATED);

        return $webhook;
    }

    private function createPayment(bool $isFullyCharged): Payment
    {
        $payment = $this->createStub(Payment::class);
        $payment->method('getStatus')->willReturn($isFullyCharged ? PaymentStatusEnum::CHARGED : PaymentStatusEnum::RESERVED);

        return $payment;
    }

    private function createPaymentFetcher(Payment $payment): CachablePaymentFetcherInterface
    {
        $paymentFetcher = $this->createMock(CachablePaymentFetcherInterface::class);
        \assert($paymentFetcher instanceof CachablePaymentFetcherInterface);
        $paymentFetcher
            ->method('getCachedPayment')
            ->willReturn($payment);

        return $paymentFetcher;
    }
}
