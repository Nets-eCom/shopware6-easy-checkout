<?php

declare(strict_types=1);

namespace NexiNets\Tests\WebhookProcessor;

use NexiNets\CheckoutApi\Model\Webhook\CancelCreated as CancelCreatedModel;
use NexiNets\CheckoutApi\Model\Webhook\Data\CancelCreatedData;
use NexiNets\CheckoutApi\Model\Webhook\EventNameEnum;
use NexiNets\WebhookProcessor\Processor\CancelCreated;
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

final class CancelCreatedTest extends TestCase
{
    public function testItProcessCancelCreatedEvent(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeast(2))->method('info')->with($this->isType('string'));
        $salesChannelContext = Generator::createSalesChannelContext();

        $orderTransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $orderTransactionStateHandler
            ->expects($this->once())
            ->method('cancel')
            ->with(
                'foo',
                $salesChannelContext->getContext()
            );

        $sut = new CancelCreated(
            $this->createTransactionRepository(OrderTransactionStates::STATE_AUTHORIZED, 'foo'),
            $orderTransactionStateHandler,
            $logger
        );

        $sut->process(
            $this->createCancelCreatedWebhookEvent($this->createCancelCreatedData('foo')),
            $salesChannelContext
        );
    }

    public function testItStopsProcessOnCancelledTransaction(): void
    {
        $transactionRepository = $this->createTransactionRepository(OrderTransactionStates::STATE_CANCELLED, '123');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeast(2))->method('info')->with($this->isType('string'));

        $webhook = $this->createCancelCreatedWebhookEvent($this->createCancelCreatedData('123'));
        $salesChannelContext = Generator::createSalesChannelContext();

        $orderTransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $orderTransactionStateHandler
            ->expects($this->never())
            ->method('cancel')
            ->with(
                'foo',
                $salesChannelContext->getContext()
            );

        $sut = new CancelCreated(
            $transactionRepository,
            $orderTransactionStateHandler,
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

        $cancelCreatedData = $this->createStub(CancelCreatedData::class);
        $cancelCreatedData->method('getPaymentId')->willReturn('123');

        $cancelCreatedModel = $this->createStub(CancelCreatedModel::class);
        $cancelCreatedModel->method('getData')->willReturn($cancelCreatedData);
        $cancelCreatedModel->method('getEvent')->willReturn(EventNameEnum::PAYMENT_CANCEL_CREATED);

        $sut = new CancelCreated(
            $transactionRepository,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(LoggerInterface::class)
        );

        $sut->process($cancelCreatedModel, Generator::createSalesChannelContext());
    }

    public function testItSupportsCancelCreatedWebhook(): void
    {
        $sut = new CancelCreated(
            $this->createStub(EntityRepository::class),
            $this->createStub(OrderTransactionStateHandler::class),
            $this->createStub(LoggerInterface::class)
        );

        $webhook = $this->createMock(CancelCreatedModel::class);
        $webhook->method('getEvent')->willReturn(EventNameEnum::PAYMENT_CANCEL_CREATED);

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

    private function createCancelCreatedData(string $paymentId): CancelCreatedData
    {
        $cancelCreatedData = $this->createMock(CancelCreatedData::class);
        $cancelCreatedData->method('getPaymentId')->willReturn($paymentId);

        return $cancelCreatedData;
    }

    private function createCancelCreatedWebhookEvent(CancelCreatedData $chargeCreatedData): CancelCreatedModel
    {
        $webhook = $this->createMock(CancelCreatedModel::class);
        $webhook->method('getData')->willReturn($chargeCreatedData);
        $webhook->method('getEvent')->willReturn(EventNameEnum::PAYMENT_CANCEL_CREATED);

        return $webhook;
    }
}
