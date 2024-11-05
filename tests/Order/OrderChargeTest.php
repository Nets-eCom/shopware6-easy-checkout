<?php

declare(strict_types=1);

namespace NexiNets\Tests\Order;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Request\FullCharge;
use NexiNets\CheckoutApi\Model\Result\ChargeResult;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\Order\OrderCharge;
use NexiNets\RequestBuilder\ChargeRequest;
use NexiNets\Tests\Order\Mother\RetrievePaymentResultMother;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

final class OrderChargeTest extends TestCase
{
    public function testItFullChargesOrder(): void
    {
        $order = $this->createOrderEntity();
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction_uuid');
        $transaction->setCustomFields([
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID => '025400006091b1ef6937598058c4e487',
        ]);
        $order->getTransactions()->add($transaction);

        $configurationProvider = $this->createConfigurationProviderMock();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApi->expects($this->once())
            ->method('retrievePayment')
            ->with('025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultMother::reserved());
        $paymentApiFactory = $this->createPaymentApiFactoryMock($paymentApi);

        $charge = new FullCharge(100);
        $chargeRequestBuilder = $this->createMock(ChargeRequest::class);
        $chargeRequestBuilder->expects($this->once())
            ->method('buildFullCharge')
            ->with($transaction)
            ->willReturn($charge);

        $paymentApi->expects($this->once())
            ->method('charge')
            ->with('025400006091b1ef6937598058c4e487', $charge)
            ->willReturn(new ChargeResult('test_charge_id'));

        $sut = new OrderCharge(
            $paymentApiFactory,
            $configurationProvider,
            $chargeRequestBuilder,
        );

        $sut->fullCharge(
            $order
        );
    }

    public function testShouldNotFullChargeOnAutoChargeEnabled(): void
    {
        $order = $this->createOrderEntity();
        $configurationProvider = $this->createConfigurationProviderMock();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApiFactory = $this->createPaymentApiFactoryMock($paymentApi);

        $chargeRequestBuilder = $this->createMock(ChargeRequest::class);

        $paymentApi->expects($this->never())->method('charge');

        $sut = new OrderCharge(
            $paymentApiFactory,
            $configurationProvider,
            $chargeRequestBuilder,
        );

        $sut->fullCharge($order);
    }

    public function testItShouldNotChargeIfNotNexiPayment(): void
    {
        $order = $this->createOrderEntity();
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction_uuid');
        $order->getTransactions()->add($transaction);

        $configurationProvider = $this->createConfigurationProviderMock();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApi->expects($this->never())
            ->method('retrievePayment');
        $paymentApiFactory = $this->createPaymentApiFactoryMock($paymentApi);

        $chargeRequestBuilder = $this->createMock(ChargeRequest::class);

        $paymentApi->expects($this->never())
            ->method('charge');

        $sut = new OrderCharge(
            $paymentApiFactory,
            $configurationProvider,
            $chargeRequestBuilder,
        );

        $sut->fullCharge($order);
    }

    public function testItShouldNotFullChargeIfPaymentAlreadyCharged(): void
    {
        $order = $this->createOrderEntity();
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction_uuid');
        $transaction->setCustomFields([
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID => '025400006091b1ef6937598058c4e487',
        ]);
        $order->getTransactions()->add($transaction);

        $configurationProvider = $this->createConfigurationProviderMock();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApi->expects($this->once())
            ->method('retrievePayment')
            ->with('025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultMother::fullyCharged());
        $paymentApiFactory = $this->createPaymentApiFactoryMock($paymentApi);

        $chargeRequestBuilder = $this->createMock(ChargeRequest::class);
        $chargeRequestBuilder->expects($this->never())
            ->method('buildFullCharge');

        $paymentApi->expects($this->never())
            ->method('charge');

        $sut = new OrderCharge(
            $paymentApiFactory,
            $configurationProvider,
            $chargeRequestBuilder,
        );

        $sut->fullCharge($order);
    }

    private function createOrderEntity(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order_uuid');
        $order->setSalesChannelId('test_sales_channel_id');
        $order->setTransactions(new OrderTransactionCollection([]));

        return $order;
    }

    private function createConfigurationProviderMock(): ConfigurationProvider|MockObject
    {
        return $this->createConfiguredMock(
            ConfigurationProvider::class,
            [
                'getSecretKey' => 'secret',
                'isLiveMode' => true,
            ],
        );
    }

    private function createPaymentApiFactoryMock(MockObject $paymentApi): PaymentApiFactory|MockObject
    {
        $paymentApiFactory = $this->createMock(PaymentApiFactory::class);
        $paymentApiFactory
            ->method('create')
            ->with('secret', true)
            ->willReturn($paymentApi);

        return $paymentApiFactory;
    }
}
