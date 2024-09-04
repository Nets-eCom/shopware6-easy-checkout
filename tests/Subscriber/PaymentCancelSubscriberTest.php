<?php

declare(strict_types=1);

namespace NexiNets\Tests\Subscriber;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Request\Cancel;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\RequestBuilder\Helper\FormatHelper;
use NexiNets\Subscriber\PaymentCancelSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;

final class PaymentCancelSubscriberTest extends TestCase
{
    public function testItCancelsTransaction(): void
    {
        $context = Context::createDefaultContext();

        $paymentId = 'foo';
        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApi->expects($this->once())
            ->method('cancel')
            ->with(
                $paymentId,
                new Cancel(1000),
            );

        $paymentApiFactory = $this->createMock(PaymentApiFactory::class);
        $paymentApiFactory->method('create')->with('secret', true)->willReturn($paymentApi);

        $configurationProvider = $this->createStub(ConfigurationProvider::class);
        $configurationProvider->method('getSecretKey')->willReturn('secret');
        $configurationProvider->method('isLiveMode')->willReturn(true);

        $transactionId = 'transactionId';

        $entitySearchResult = $this->createStub(EntitySearchResult::class);
        $entitySearchResult->method('first')->willReturn(
            $this->createOrder($transactionId, OrderTransactionStates::STATE_AUTHORIZED, $paymentId)
        );
        $entityRepository = $this->createStub(EntityRepository::class);
        $entityRepository->method('search')->willReturn($entitySearchResult);

        $orderTransactionHandler = $this->createMock(OrderTransactionStateHandler::class);
        $orderTransactionHandler
            ->expects($this->once())
            ->method('cancel')
            ->with(
                $transactionId,
                $context
            );

        $sut = new PaymentCancelSubscriber(
            $paymentApiFactory,
            $configurationProvider,
            $entityRepository,
            $orderTransactionHandler,
            new FormatHelper()
        );

        $sut->onOrderCancel(
            $this->createStateTransitionEvent(
                $context,
                $transactionId,
                OrderStates::STATE_CANCELLED,
                OrderDefinition::ENTITY_NAME
            )
        );
    }

    public function testItSubscribesToStateMachineTransitionEvent(): void
    {
        $sut = new PaymentCancelSubscriber(
            $this->createStub(PaymentApiFactory::class),
            $this->createStub(ConfigurationProvider::class),
            $this->createStub(EntityRepository::class),
            $this->createStub(OrderTransactionStateHandler::class),
            $this->createStub(FormatHelper::class)
        );

        $this->assertSame(
            [
                StateMachineTransitionEvent::class => 'onOrderCancel',
            ],
            $sut::getSubscribedEvents()
        );
    }

    private function createStateTransitionEvent(
        Context $context,
        string $transactionId,
        string $toPlaceTechnicalName,
        string $entityName
    ): StateMachineTransitionEvent {
        $fromPlace = new StateMachineStateEntity();
        $fromPlace->setTechnicalName(OrderStates::STATE_OPEN);

        $toPlace = new StateMachineStateEntity();
        $toPlace->setTechnicalName($toPlaceTechnicalName);

        return new StateMachineTransitionEvent(
            $entityName,
            $transactionId,
            $fromPlace,
            $toPlace,
            $context
        );
    }

    private function createOrderTransaction(string $transactionId, string $transactionState, string $paymentId): OrderTransactionEntity
    {
        $state = new StateMachineStateEntity();
        $state->setTechnicalName($transactionState);

        $transaction = new OrderTransactionEntity();
        $transaction->setId($transactionId);
        $transaction->setCustomFields(
            [
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID => $paymentId,
            ]
        );
        $transaction->setStateMachineState($state);
        $transaction->setUniqueIdentifier('uniqueTransactionIdentifier');
        $transaction->setAmount(new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $transaction;
    }

    public function createOrder(string $transactionId, string $orderTransactionState, string $paymentId): OrderEntity
    {
        $order = new OrderEntity();
        $order->setTransactions(
            new OrderTransactionCollection(
                [$this->createOrderTransaction($transactionId, $orderTransactionState, $paymentId)]
            )
        );
        $order->setSalesChannelId('salesChannelId');

        return $order;
    }
}
