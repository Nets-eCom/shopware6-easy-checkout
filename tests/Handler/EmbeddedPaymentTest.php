<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Handler;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Handler\EmbeddedPayment;
use Nexi\Checkout\RequestBuilder\Helper\FormatHelper;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Result\RetrievePayment\OrderDetails;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePaymentResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

final class EmbeddedPaymentTest extends TestCase
{
    private const PAYMENT_ID = '123';

    private const ORDER_TRANSACTION_ID = '567';

    private const CART_TOKEN = 'test_token';

    public function testValidate(): void
    {
        $cart = new Cart(self::CART_TOKEN);
        $cart->setPrice(new CartPrice(100, 100, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $payment = $this->createStub(Payment::class);
        $payment->method('getPaymentId')->willReturn(self::PAYMENT_ID);
        $payment->method('getMyReference')->willReturn(self::CART_TOKEN);
        $payment->method('getOrderDetails')->willReturn(new OrderDetails(10000, 'EUR'));

        $result = $this->createStub(RetrievePaymentResult::class);
        $result->method('getPayment')->willReturn($payment);

        $paymentApi = $this->createStub(PaymentApi::class);
        $paymentApi->method('retrievePayment')->willReturn($result);

        $paymentApiFactory = $this->createStub(PaymentApiFactory::class);
        $paymentApiFactory->method('create')->willReturn($paymentApi);

        $sut = new EmbeddedPayment(
            $paymentApiFactory,
            $this->createStub(ConfigurationProvider::class),
            new FormatHelper(),
            $this->createStub(EntityRepository::class),
            $this->createStub(OrderTransactionStateHandler::class),
            $this->createStub(LoggerInterface::class),
        );


        $validateStruct = $sut->validate(
            $cart,
            new RequestDataBag([
                'nexiPaymentId' => self::PAYMENT_ID,
            ]),
            Generator::generateSalesChannelContext()
        );

        $this->assertInstanceOf(ArrayStruct::class, $validateStruct);
        $this->assertEquals(self::PAYMENT_ID, $validateStruct->get('paymentId'));
    }

    public function testPayCallesOrderTransactionUpdate(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $context = $salesChannelContext->getContext();

        $orderTransactionRepository = $this->createMock(EntityRepository::class);
        $orderTransactionRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                [
                    [
                        'id' => self::ORDER_TRANSACTION_ID,
                        'customFields' => [
                            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID => self::PAYMENT_ID,
                            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_ORDER => [],
                            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_REFUNDED => [],
                        ],
                    ],
                ],
                $context
            );

        $orderTransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $orderTransactionStateHandler
            ->expects($this->once())
            ->method('process')
            ->with(self::ORDER_TRANSACTION_ID, $context);

        $order = new OrderEntity();
        $order->setSalesChannelId($salesChannelContext->getSalesChannelId());

        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setOrder($order);
        $orderTransaction->setId(self::ORDER_TRANSACTION_ID);
        $orderTransaction->setCustomFields([
            'nexi_checkout_payment_id' => self::PAYMENT_ID,
        ]);

        $sut = new EmbeddedPayment(
            $this->createStub(PaymentApiFactory::class),
            $this->createStub(ConfigurationProvider::class),
            new FormatHelper(),
            $orderTransactionRepository,
            $orderTransactionStateHandler,
            $this->createStub(LoggerInterface::class),
        );

        $sut->pay(
            new Request(),
            new PaymentTransactionStruct(self::ORDER_TRANSACTION_ID, null),
            $context,
            new ArrayStruct([
                'paymentId' => self::PAYMENT_ID,
            ])
        );
    }
}
