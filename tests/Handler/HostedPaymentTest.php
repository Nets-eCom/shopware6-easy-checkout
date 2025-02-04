<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Handler;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Handler\HostedPayment;
use Nexi\Checkout\RequestBuilder\PaymentRequest;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Request\Item;
use NexiCheckout\Model\Request\Payment as RequestPayment;
use NexiCheckout\Model\Request\Payment\HostedCheckout;
use NexiCheckout\Model\Request\Payment\Order;
use NexiCheckout\Model\Result\Payment\PaymentWithHostedCheckoutResult;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\Summary;
use NexiCheckout\Model\Result\RetrievePaymentResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class HostedPaymentTest extends TestCase
{
    private const HOSTED_PAYMENT_RETURN_URL = 'https://example.com/returnUrl';

    private const PAYMENT_ID = '123';

    private const ORDER_TRANSACTION_ID = '567';

    public function testPayReturnsRedirectResponse(): void
    {
        $hostedPaymentResult = new PaymentWithHostedCheckoutResult(
            self::PAYMENT_ID,
            self::HOSTED_PAYMENT_RETURN_URL
        );

        $paymentApi = $this->createStub(PaymentApi::class);
        $paymentApi->method('createPayment')->willReturn($hostedPaymentResult);

        $paymentApiFactory = $this->createStub(PaymentApiFactory::class);
        $paymentApiFactory->method('create')->willReturn($paymentApi);

        $salesChannelContext = Generator::createSalesChannelContext();
        $context = $salesChannelContext->getContext();

        $order = new OrderEntity();
        $order->setSalesChannelId($salesChannelContext->getSalesChannelId());

        $orderTransactionEntity = new OrderTransactionEntity();
        $orderTransactionEntity->setId(self::ORDER_TRANSACTION_ID);
        $orderTransactionEntity->setOrder($order);

        $paymentRequest = $this->createPaymentRequest();
        $requestBuilder = $this->createStub(PaymentRequest::class);
        $requestBuilder->method('build')->willReturn($paymentRequest);

        $orderTransactionRepository = $this->createOrderTransactionRepository($orderTransactionEntity, $context);
        $orderTransactionRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                [
                    [
                        'id' => self::ORDER_TRANSACTION_ID,
                        'customFields' => [
                            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID => self::PAYMENT_ID,
                            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_ORDER => $paymentRequest->getOrder(),
                            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_REFUNDED => [],
                        ],
                    ],
                ],
                $context
            );

        $sut = new HostedPayment(
            $requestBuilder,
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class),
            $orderTransactionRepository,
            $this->createStub(OrderTransactionStateHandler::class),
        );

        $result = $sut->pay(
            new Request(),
            new PaymentTransactionStruct(self::ORDER_TRANSACTION_ID, 'returnUrl'),
            $context,
            null
        );

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame(self::HOSTED_PAYMENT_RETURN_URL, $result->getTargetUrl());
    }

    public function testPayThrowsPaymentException(): void
    {
        $this->expectException(PaymentException::class);

        $paymentApi = $this->createStub(PaymentApi::class);
        $paymentApi->method('createPayment')->willThrowException(new PaymentApiException());

        $paymentApiFactory = $this->createStub(PaymentApiFactory::class);
        $paymentApiFactory->method('create')->willReturn($paymentApi);

        $sut = new HostedPayment(
            $this->createStub(PaymentRequest::class),
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class),
            $this->createStub(EntityRepository::class),
            $this->createStub(OrderTransactionStateHandler::class),
        );

        $sut->pay(
            new Request(),
            new PaymentTransactionStruct(self::ORDER_TRANSACTION_ID, 'returnUrl'),
            Generator::createSalesChannelContext()->getContext(),
            null
        );
    }

    public function testFinalize(): void
    {
        $payment = $this->createStub(Payment::class);
        $payment->method('getPaymentId')->willReturn(self::PAYMENT_ID);
        $payment->method('getSummary')->willReturn(
            new Summary(1, 0, 0, 0)
        );

        $result = $this->createStub(RetrievePaymentResult::class);
        $result->method('getPayment')->willReturn($payment);

        $paymentApi = $this->createStub(PaymentApi::class);
        $paymentApi->method('retrievePayment')->willReturn($result);

        $paymentApiFactory = $this->createStub(PaymentApiFactory::class);
        $paymentApiFactory->method('create')->willReturn($paymentApi);

        $salesChannelContext = Generator::createSalesChannelContext();
        $context = $salesChannelContext->getContext();

        $orderTransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $orderTransactionStateHandler
            ->expects($this->once())
            ->method('authorize')
            ->with(self::ORDER_TRANSACTION_ID, $context);

        $order = new OrderEntity();
        $order->setSalesChannelId($salesChannelContext->getSalesChannelId());

        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setOrder($order);
        $orderTransaction->setId(self::ORDER_TRANSACTION_ID);
        $orderTransaction->setCustomFields([
            'nexi_checkout_payment_id' => self::PAYMENT_ID,
        ]);

        $sut = new HostedPayment(
            $this->createStub(PaymentRequest::class),
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class),
            $this->createOrderTransactionRepository($orderTransaction, $context),
            $orderTransactionStateHandler,
        );

        $sut->finalize(
            new Request(),
            new PaymentTransactionStruct(self::ORDER_TRANSACTION_ID, 'returnUrl'),
            $context,
        );
    }

    public function testFinalizeThrowsPaymentException(): void
    {
        $this->expectException(PaymentException::class);

        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId(self::ORDER_TRANSACTION_ID);
        $orderTransaction->setCustomFields([
            'nexi_checkout_payment_id' => self::PAYMENT_ID,
        ]);

        $payment = $this->createStub(Payment::class);
        $payment->method('getPaymentId')->willReturn(self::PAYMENT_ID);
        $payment->method('getSummary')->willReturn(new Summary(0, 0, 0, 0));

        $result = $this->createStub(RetrievePaymentResult::class);
        $result->method('getPayment')->willReturn($payment);

        $paymentApi = $this->createStub(PaymentApi::class);
        $paymentApi->method('retrievePayment')->willReturn($result);

        $paymentApiFactory = $this->createStub(PaymentApiFactory::class);
        $paymentApiFactory->method('create')->willReturn($paymentApi);

        $sut = new HostedPayment(
            $this->createStub(PaymentRequest::class),
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class),
            $this->createStub(EntityRepository::class),
            $this->createMock(OrderTransactionStateHandler::class),
        );

        $sut->finalize(
            new Request(),
            new PaymentTransactionStruct(self::ORDER_TRANSACTION_ID, 'returnUrl'),
            Generator::createSalesChannelContext()->getContext(),
        );
    }

    /**
     * @return EntityRepository<OrderTransactionCollection>|MockObject
     */
    private function createOrderTransactionRepository(OrderTransactionEntity $orderTransactionEntity, Context $context): EntityRepository|MockObject
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                OrderTransactionEntity::class,
                1,
                new EntityCollection([$orderTransactionEntity]),
                null,
                new Criteria(),
                $context
            )
        );

        return $repository;
    }

    private function createPaymentRequest(): RequestPayment
    {
        return new RequestPayment(
            new Order(
                [
                    new Item(
                        'item',
                        1,
                        'pcs',
                        100,
                        100,
                        100,
                        'ref'
                    ),
                ],
                'SEK',
                100
            ),
            new HostedCheckout(
                'https://shop.example.com/returnUrl',
                'https://shop.example.com/cancelUrl',
                'https://shop.example.com/termsUrl',
            )
        );
    }
}
