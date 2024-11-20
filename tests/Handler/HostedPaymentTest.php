<?php

declare(strict_types=1);

namespace NexiNets\Tests\Handler;

use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Request\Item;
use NexiNets\CheckoutApi\Model\Request\Payment as RequestPayment;
use NexiNets\CheckoutApi\Model\Request\Payment\HostedCheckout;
use NexiNets\CheckoutApi\Model\Request\Payment\Order;
use NexiNets\CheckoutApi\Model\Result\Payment\PaymentWithHostedCheckoutResult;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Summary;
use NexiNets\CheckoutApi\Model\Result\RetrievePaymentResult;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\Handler\HostedPayment;
use NexiNets\RequestBuilder\PaymentRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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

        $orderTransactionEntity = new OrderTransactionEntity();
        $orderTransactionEntity->setId(self::ORDER_TRANSACTION_ID);

        $paymentRequest = $this->createPaymentRequest();
        $requestBuilder = $this->createStub(PaymentRequest::class);
        $requestBuilder->method('build')->willReturn($paymentRequest);

        $salesChannelContext = Generator::createSalesChannelContext();

        $orderTransactionRepository = $this->createOrderTransactionRepository();
        $orderTransactionRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                [
                    [
                        'id' => self::ORDER_TRANSACTION_ID,
                        'customFields' => [
                            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID => self::PAYMENT_ID,
                            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_ORDER => $paymentRequest->getOrder(),
                        ],
                    ],
                ],
                $salesChannelContext->getContext()
            );

        $sut = new HostedPayment(
            $requestBuilder,
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class),
            $orderTransactionRepository,
            $this->createStub(OrderTransactionStateHandler::class),
        );

        $result = $sut->pay(
            new AsyncPaymentTransactionStruct(
                $orderTransactionEntity,
                new OrderEntity(),
                self::HOSTED_PAYMENT_RETURN_URL
            ),
            $this->createStub(RequestDataBag::class),
            $salesChannelContext
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
            $this->createOrderTransactionRepository(),
            $this->createStub(OrderTransactionStateHandler::class),
        );

        $sut->pay(
            $this->createStub(AsyncPaymentTransactionStruct::class),
            $this->createStub(RequestDataBag::class),
            $this->createStub(SalesChannelContext::class),
        );
    }

    public function testFinalize(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId(self::ORDER_TRANSACTION_ID);
        $orderTransaction->setCustomFields([
            'nexi_nets_payment_id' => self::PAYMENT_ID,
        ]);

        $asyncPaymentTransaction = new AsyncPaymentTransactionStruct(
            $orderTransaction,
            new OrderEntity(),
            self::HOSTED_PAYMENT_RETURN_URL
        );

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

        $orderTransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $orderTransactionStateHandler
            ->expects($this->once())
            ->method('authorize')
            ->with(self::ORDER_TRANSACTION_ID, $salesChannelContext->getContext());

        $sut = new HostedPayment(
            $this->createStub(PaymentRequest::class),
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class),
            $this->createOrderTransactionRepository(),
            $orderTransactionStateHandler,
        );

        $sut->finalize(
            $asyncPaymentTransaction,
            $this->createStub(Request::class),
            $salesChannelContext,
        );
    }

    public function testFinalizeThrowsPaymentException(): void
    {
        $this->expectException(PaymentException::class);

        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId(self::ORDER_TRANSACTION_ID);
        $orderTransaction->setCustomFields([
            'nexi_nets_payment_id' => self::PAYMENT_ID,
        ]);

        $asyncPaymentTransaction = new AsyncPaymentTransactionStruct(
            $orderTransaction,
            new OrderEntity(),
            self::HOSTED_PAYMENT_RETURN_URL
        );

        $payment = $this->createStub(Payment::class);
        $payment->method('getPaymentId')->willReturn(self::PAYMENT_ID);
        $payment->method('getSummary')->willReturn(new Summary(0, 0, 0, 0));

        $result = $this->createStub(RetrievePaymentResult::class);
        $result->method('getPayment')->willReturn($payment);

        $paymentApi = $this->createStub(PaymentApi::class);
        $paymentApi->method('retrievePayment')->willReturn($result);

        $paymentApiFactory = $this->createStub(PaymentApiFactory::class);
        $paymentApiFactory->method('create')->willReturn($paymentApi);

        $salesChannelContext = Generator::createSalesChannelContext();

        $orderTransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);

        $sut = new HostedPayment(
            $this->createStub(PaymentRequest::class),
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class),
            $this->createOrderTransactionRepository(),
            $orderTransactionStateHandler,
        );

        $sut->finalize(
            $asyncPaymentTransaction,
            new Request(),
            $salesChannelContext,
        );
    }

    /**
     * @return EntityRepository<OrderTransactionCollection>|MockObject
     */
    private function createOrderTransactionRepository(): EntityRepository|MockObject
    {
        return $this->createMock(EntityRepository::class);
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
