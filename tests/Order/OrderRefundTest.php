<?php

declare(strict_types=1);

namespace NexiNets\Tests\Order;

use NexiNets\Administration\Model\RefundData;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Request\FullRefundCharge;
use NexiNets\CheckoutApi\Model\Request\PartialRefundCharge;
use NexiNets\CheckoutApi\Model\Request\RefundCharge;
use NexiNets\CheckoutApi\Model\Result\RefundChargeResult;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Core\Content\NetsCheckout\Event\RefundChargeSend;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\Fetcher\PaymentFetcherInterface;
use NexiNets\Order\OrderRefund;
use NexiNets\RequestBuilder\Helper\FormatHelper;
use NexiNets\RequestBuilder\RefundRequest;
use NexiNets\Tests\CheckoutApi\Fixture\RetrievePaymentResultFixture;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class OrderRefundTest extends TestCase
{
    public function testItFullyRefundsChargedOrder(): void
    {
        $order = $this->createOrderEntity();

        $api = $this->createMock(PaymentApi::class);
        $api
            ->expects($this->once())
            ->method('refundCharge')
            ->with(
                'test_charge_id',
                new FullRefundCharge(10000)
            )
            ->willReturn(new RefundChargeResult('foo'));

        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('fetchPayment')
            ->with('test_sales_channel_id', '025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultFixture::fullyCharged()->getPayment());

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new RefundChargeSend(
                $order,
                $order->getTransactions()->first(),
                100.00
            ));

        $sut = new OrderRefund(
            $fetcher,
            $this->createPaymentApiFactory($api),
            $this->createConfigurationProvider(),
            $this->createRefundChargeRequestBuilder(),
            $this->createMock(EntityRepository::class),
            $eventDispatcher,
            $this->createMock(LoggerInterface::class)
        );

        $sut->fullRefund($order);
    }

    public function testItFullyRefundsAllCharges(): void
    {
        $order = $this->createOrderEntity();

        $payment = RetrievePaymentResultFixture::fullyChargedWithMultipleCharges()->getPayment();
        $invokedCount = $this->exactly(\count($payment->getCharges()));

        $api = $this->createMock(PaymentApi::class);
        $api
            ->expects($invokedCount)
            ->method('refundCharge')
            ->willReturnCallback(function (string $chargeId, RefundCharge $refund) use ($payment, $invokedCount) {
                $charge = $payment->getCharges()[$invokedCount->numberOfInvocations() - 1];
                $this->assertSame($charge->getChargeId(), $chargeId);
                $this->assertSame($charge->getAmount(), $refund->getAmount());

                return new RefundChargeResult('foo');
            });

        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('fetchPayment')
            ->with('test_sales_channel_id', '025400006091b1ef6937598058c4e487')
            ->willReturn($payment);

        $transaction = $order->getTransactions()->first();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new RefundChargeSend(
                $order,
                $transaction,
                $transaction->getAmount()->getTotalPrice()
            ));

        $sut = new OrderRefund(
            $fetcher,
            $this->createPaymentApiFactory($api),
            $this->createConfigurationProvider(),
            $this->createRefundChargeRequestBuilder(),
            $this->createMock(EntityRepository::class),
            $eventDispatcher,
            $this->createMock(LoggerInterface::class)
        );

        $sut->fullRefund($order);
    }

    public function testItShouldNotFullyRefundIfAlreadyRefunded(): void
    {
        $order = $this->createOrderEntity();

        $api = $this->createMock(PaymentApi::class);
        $api
            ->expects($this->never())
            ->method('refundCharge');

        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('fetchPayment')
            ->with('test_sales_channel_id', '025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultFixture::fullyRefunded()->getPayment());

        $sut = new OrderRefund(
            $fetcher,
            $this->createPaymentApiFactory($api),
            $this->createConfigurationProvider(),
            $this->createRefundChargeRequestBuilder(),
            $this->createMock(EntityRepository::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createMock(LoggerInterface::class)
        );

        $sut->fullRefund($order);
    }

    public function testItShouldNotRefundFullRefundIfRefundPartially(): void
    {
        $order = $this->createOrderEntity();

        $api = $this->createMock(PaymentApi::class);
        $api
            ->expects($this->never())
            ->method('refundCharge');

        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('fetchPayment')
            ->with('test_sales_channel_id', '025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultFixture::partiallyRefunded()->getPayment());

        $sut = new OrderRefund(
            $fetcher,
            $this->createPaymentApiFactory($api),
            $this->createConfigurationProvider(),
            $this->createRefundChargeRequestBuilder(),
            $this->createMock(EntityRepository::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createMock(LoggerInterface::class)
        );

        $sut->fullRefund($order);
    }

    public function testItShouldPartiallyRefundWithoutItems(): void
    {
        $order = $this->createOrderEntity();

        $invokedCount = $this->exactly(3);
        $api = $this->createMock(PaymentApi::class);
        $api
            ->expects($invokedCount)
            ->method('refundCharge')
            ->willReturnCallback(function (string $chargeId, PartialRefundCharge $refund) use ($invokedCount) {
                if ($invokedCount->numberOfInvocations() === 1) {
                    $this->assertSame('test_charge_1', $chargeId);
                    $this->assertSame(100, $refund->getAmount());
                }

                if ($invokedCount->numberOfInvocations() === 2) {
                    $this->assertSame('test_charge_2', $chargeId);
                    $this->assertSame(300, $refund->getAmount());
                }

                if ($invokedCount->numberOfInvocations() === 3) {
                    $this->assertSame('test_charge_3', $chargeId);
                    $this->assertSame(199, $refund->getAmount());
                }

                return new RefundChargeResult('foo');
            });

        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('fetchPayment')
            ->with('test_sales_channel_id', '025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultFixture::fullyChargedWithMultipleCharges()->getPayment());

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new RefundChargeSend(
                $order,
                $order->getTransactions()->first(),
                5.99
            ));

        $sut = new OrderRefund(
            $fetcher,
            $this->createPaymentApiFactory($api),
            $this->createConfigurationProvider(),
            $this->createRefundChargeRequestBuilder(),
            $this->createMock(EntityRepository::class),
            $eventDispatcher,
            $this->createMock(LoggerInterface::class)
        );

        $refundData = new RefundData(5.99);
        $refundData->setContext(Context::createDefaultContext());

        $sut->partialRefund($order, $refundData);
    }

    private function createOrderEntity(): OrderEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction_uuid');
        $transaction->setAmount(
            new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection([]), 1)
        );
        $transaction->setCustomFields([
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID => '025400006091b1ef6937598058c4e487',
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_ORDER => [
                'items' => [
                    [
                        'reference' => 'foo',
                        'name' => 'foo',
                        'unitPrice' => 100,
                        'taxAmount' => 20,
                    ],
                ],
                'refundedItems' => [],
                'chargedItems' => [],
            ],
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_REFUNDED => [
                'test_charge_3' => 400,
            ],
        ]);

        $order = new OrderEntity();
        $order->setId('order_uuid');
        $order->setSalesChannelId('test_sales_channel_id');
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        return $order;
    }

    private function createConfigurationProvider(): ConfigurationProvider|MockObject
    {
        return $this->createConfiguredMock(
            ConfigurationProvider::class,
            [
                'getSecretKey' => 'secret',
                'isLiveMode' => true,
            ],
        );
    }

    private function createPaymentApiFactory(MockObject $paymentApi): PaymentApiFactory|MockObject
    {
        $paymentApiFactory = $this->createMock(PaymentApiFactory::class);
        $paymentApiFactory
            ->method('create')
            ->with('secret', true)
            ->willReturn($paymentApi);

        return $paymentApiFactory;
    }

    private function createRefundChargeRequestBuilder(): RefundRequest
    {
        return new RefundRequest(new FormatHelper());
    }
}
