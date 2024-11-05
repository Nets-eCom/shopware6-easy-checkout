<?php

declare(strict_types=1);

namespace NexiNets\Tests\Order;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Request\FullRefundCharge;
use NexiNets\CheckoutApi\Model\Result\RefundChargeResult;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\Order\OrderRefund;
use NexiNets\RequestBuilder\RefundRequest;
use NexiNets\Tests\Order\Mother\RetrievePaymentResultMother;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

final class OrderRefundTest extends TestCase
{
    public function testItFullyRefundsChargedOrder(): void
    {
        $order = $this->createOrderEntity();

        $api = $this->createMock(PaymentApi::class);
        $api->expects($this->once())
            ->method('retrievePayment')
            ->with('025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultMother::fullyCharged());
        $api
            ->expects($this->once())
            ->method('refundCharge')
            ->with(
                'test_charge_id',
                new FullRefundCharge(10000)
            )
            ->willReturn(new RefundChargeResult('foo'));

        $configurationProvider = $this->createConfigurationProvider();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $sut = new OrderRefund(
            $this->createPaymentApiFactory($api),
            $configurationProvider,
            new RefundRequest(),
        );

        $sut->fullRefund($order);
    }

    public function testItShouldNotFullyRefundIfAlreadyRefunded(): void
    {
        $order = $this->createOrderEntity();

        $api = $this->createMock(PaymentApi::class);
        $api->expects($this->once())
            ->method('retrievePayment')
            ->with('025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultMother::fullyRefunded());
        $api
            ->expects($this->never())
            ->method('refundCharge');

        $configurationProvider = $this->createConfigurationProvider();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $sut = new OrderRefund(
            $this->createPaymentApiFactory($api),
            $configurationProvider,
            new RefundRequest(),
        );

        $sut->fullRefund($order);
    }

    public function testItShouldNotRefundFullRefundIfRefundPartially(): void
    {
        $order = $this->createOrderEntity();

        $api = $this->createMock(PaymentApi::class);
        $api->expects($this->once())
            ->method('retrievePayment')
            ->with('025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultMother::partiallyRefunded());
        $api
            ->expects($this->never())
            ->method('refundCharge');

        $configurationProvider = $this->createConfigurationProvider();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $sut = new OrderRefund(
            $this->createPaymentApiFactory($api),
            $configurationProvider,
            new RefundRequest(),
        );

        $sut->fullRefund($order);
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
}
