<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\WebhookProcessor;

use Nexi\Checkout\Fetcher\CachablePaymentFetcherInterface;
use Nexi\Checkout\WebhookProcessor\Processor\CheckoutCompleted;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentDetails;
use NexiCheckout\Model\Webhook\CheckoutCompleted as CheckoutCompletedModel;
use NexiCheckout\Model\Webhook\Data\CheckoutCompletedData;
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

final class CheckoutCompletedTest extends TestCase
{
    public function testItProcessCheckoutCompletedOnSupportedMethodsEvent(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeast(2))->method('info')->with($this->isType('string'));

        $paymentDetails = $this->createStub(PaymentDetails::class);
        $paymentDetails->method('getPaymentMethod')->willReturn('Swish');

        $payment = $this->createStub(Payment::class);
        $payment->method('getPaymentDetails')->willReturn($paymentDetails);

        $paymentFetcher = $this->createPaymentFetcher($payment);
        $salesChannelContext = Generator::createSalesChannelContext();

        $orderTransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $orderTransactionStateHandler
            ->expects($this->once())
            ->method('authorize')
            ->with(
                'foo',
                $salesChannelContext->getContext()
            );

        $sut = new CheckoutCompleted(
            $this->createTransactionRepository(OrderTransactionStates::STATE_OPEN, 'foo'),
            $orderTransactionStateHandler,
            $paymentFetcher,
            $logger
        );

        $sut->process(
            $this->createWebhook($this->createCheckoutCompletedData('foo')),
            $salesChannelContext
        );
    }

    public function testItStopsOnUnsupportedMethods(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info')->with($this->isType('string'));

        $paymentDetails = $this->createStub(PaymentDetails::class);
        $paymentDetails->method('getPaymentMethod')->willReturn('Card');

        $payment = $this->createStub(Payment::class);
        $payment->method('getPaymentDetails')->willReturn($paymentDetails);

        $paymentFetcher = $this->createPaymentFetcher($payment);
        $salesChannelContext = Generator::createSalesChannelContext();

        $orderTransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $orderTransactionStateHandler
            ->expects($this->never())
            ->method('authorize')
            ->with(
                'foo',
                $salesChannelContext->getContext()
            );

        $sut = new CheckoutCompleted(
            $this->createTransactionRepository(OrderTransactionStates::STATE_OPEN, 'foo'),
            $orderTransactionStateHandler,
            $paymentFetcher,
            $logger
        );

        $sut->process(
            $this->createWebhook($this->createCheckoutCompletedData('foo')),
            $salesChannelContext
        );
    }

    public function testItSupportsCheckoutCompletedWebhook(): void
    {
        $sut = new CheckoutCompleted(
            $this->createTransactionRepository(OrderTransactionStates::STATE_OPEN, 'foo'),
            $this->createStub(OrderTransactionStateHandler::class),
            $this->createStub(CachablePaymentFetcherInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $webhook = $this->createStub(CheckoutCompletedModel::class);
        $webhook->method('getEvent')->willReturn(EventNameEnum::PAYMENT_CHECKOUT_COMPLETED);

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

    private function createCheckoutCompletedData(string $paymentId): CheckoutCompletedData
    {
        $checkoutCompletedData = $this->createMock(CheckoutCompletedData::class);
        $checkoutCompletedData->method('getPaymentId')->willReturn($paymentId);

        return $checkoutCompletedData;
    }

    private function createWebhook(CheckoutCompletedData $checkoutCompletedData): CheckoutCompletedModel
    {
        $webhook = $this->createMock(CheckoutCompletedModel::class);
        $webhook->method('getData')->willReturn($checkoutCompletedData);
        $webhook->method('getEvent')->willReturn(EventNameEnum::PAYMENT_CHECKOUT_COMPLETED);

        return $webhook;
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
