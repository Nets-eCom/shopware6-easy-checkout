<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Order;

use Nexi\Checkout\Administration\Model\ChargeData;
use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Order\OrderCharge;
use Nexi\Checkout\RequestBuilder\ChargeRequest;
use Nexi\Checkout\Tests\Fixture\RetrievePaymentResultFixture;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Request\FullCharge;
use NexiCheckout\Model\Request\Item;
use NexiCheckout\Model\Request\PartialCharge;
use NexiCheckout\Model\Result\ChargeResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class OrderChargeTest extends TestCase
{
    public function testItFullChargesOrder(): void
    {
        $configurationProvider = $this->createConfigurationProviderMock();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('fetchPayment')
            ->with('test_sales_channel_id', '025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultFixture::reserved()->getPayment());

        $fullCharge = new FullCharge(10000);
        $chargeRequestBuilder = $this->createChargeRequestBuilderMock();
        $chargeRequestBuilder->expects($this->once())
            ->method('buildFullCharge')
            ->with($this->isInstanceOf(OrderTransactionEntity::class))
            ->willReturn($fullCharge);

        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApi->expects($this->once())
            ->method('charge')
            ->with('025400006091b1ef6937598058c4e487', $fullCharge)
            ->willReturn(new ChargeResult('test_charge_id'));

        $sut = new OrderCharge(
            $fetcher,
            $this->createPaymentApiFactoryMock($paymentApi),
            $configurationProvider,
            $chargeRequestBuilder,
            $this->createMock(EventDispatcherInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $sut->fullCharge($this->createOrderEntity());
    }

    public function testShouldNotFullChargeOnAutoChargeEnabled(): void
    {
        $configurationProvider = $this->createConfigurationProviderMock();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(true);

        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApi->expects($this->never())->method('charge');

        $sut = new OrderCharge(
            $this->createMock(PaymentFetcherInterface::class),
            $this->createPaymentApiFactoryMock($paymentApi),
            $configurationProvider,
            $this->createChargeRequestBuilderMock(),
            $this->createMock(EventDispatcherInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $sut->fullCharge($this->createOrderEntity());
    }

    public function testItShouldNotChargeIfNotNexiPayment(): void
    {
        $order = $this->createOrderEntity();
        $order->getTransactions()->first()->setCustomFields([]);

        $configurationProvider = $this->createConfigurationProviderMock();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects($this->never())->method('fetchPayment');

        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApi->expects($this->never())->method('charge');

        $sut = new OrderCharge(
            $fetcher,
            $this->createPaymentApiFactoryMock($paymentApi),
            $configurationProvider,
            $this->createChargeRequestBuilderMock(),
            $this->createMock(EventDispatcherInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $sut->fullCharge($order);
    }

    public function testItShouldNotFullChargeIfPaymentAlreadyCharged(): void
    {
        $configurationProvider = $this->createConfigurationProviderMock();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('fetchPayment')
            ->with('test_sales_channel_id', '025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultFixture::fullyCharged()->getPayment());

        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApi->expects($this->never())->method('charge');

        $sut = new OrderCharge(
            $fetcher,
            $this->createPaymentApiFactoryMock($paymentApi),
            $configurationProvider,
            $this->createChargeRequestBuilderMock(),
            $this->createMock(EventDispatcherInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $sut->fullCharge($this->createOrderEntity());
    }

    public function testItPariallyCharges(): void
    {
        $configurationProvider = $this->createConfigurationProviderMock();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('fetchPayment')
            ->with('test_sales_channel_id', '025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultFixture::reserved()->getPayment());

        $chargeData = new ChargeData(5000);
        $partialCharge = new PartialCharge([new Item('test', 1, 'pcs', 5000, 5000, 5000, 'test')]);
        $chargeRequestBuilder = $this->createChargeRequestBuilderMock();
        $chargeRequestBuilder->expects($this->once())
            ->method('buildPartialCharge')
            ->with($this->isInstanceOf(OrderTransactionEntity::class), $chargeData)
            ->willReturn($partialCharge);

        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApi->expects($this->once())
            ->method('charge')
            ->with('025400006091b1ef6937598058c4e487', $partialCharge)
            ->willReturn(new ChargeResult('test_charge_id'));

        $sut = new OrderCharge(
            $fetcher,
            $this->createPaymentApiFactoryMock($paymentApi),
            $configurationProvider,
            $chargeRequestBuilder,
            $this->createMock(EventDispatcherInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $sut->partialCharge($this->createOrderEntity(), $chargeData);
    }

    private function createOrderEntity(): OrderEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction_uuid');
        $transaction->setAmount(
            new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection([]), 1)
        );
        $transaction->setCustomFields([
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID => '025400006091b1ef6937598058c4e487',
        ]);

        $order = new OrderEntity();
        $order->setId('order_uuid');
        $order->setSalesChannelId('test_sales_channel_id');
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

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

    private function createChargeRequestBuilderMock(): ChargeRequest|MockObject
    {
        return $this->createMock(ChargeRequest::class);
    }
}
