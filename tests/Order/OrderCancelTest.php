<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Order;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Order\Exception\OrderCancelException;
use Nexi\Checkout\Order\OrderCancel;
use Nexi\Checkout\RequestBuilder\CancelRequest;
use Nexi\Checkout\RequestBuilder\Helper\FormatHelper;
use Nexi\Checkout\Tests\Fixture\RetrievePaymentResultFixture;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Request\Cancel;
use PHPUnit\Framework\MockObject\Exception;
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

final class OrderCancelTest extends TestCase
{
    /**
     * @throws OrderCancelException
     * @throws Exception
     */
    public function testItCanCancelOrderPayment(): void
    {
        $api = $this->createMock(PaymentApi::class);
        $api
            ->expects($this->once())
            ->method('cancel')
            ->with(
                '025400006091b1ef6937598058c4e487',
                new Cancel(10000)
            );

        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('fetchPayment')
            ->with('test_sales_channel_id', '025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultFixture::reserved()->getPayment());


        $sut = new OrderCancel(
            $fetcher,
            $this->createPaymentApiFactory($api),
            $this->createConfigurationProvider(),
            $this->createCancelRequestBuilder(),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(LoggerInterface::class)
        );

        $sut->cancel($this->createOrderEntity());
    }

    public function testItShouldNotCancelNotReserved(): void
    {
        $api = $this->createMock(PaymentApi::class);
        $api
            ->expects($this->never())
            ->method('cancel');

        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('fetchPayment')
            ->with('test_sales_channel_id', '025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultFixture::fullyCharged()->getPayment());

        $sut = new OrderCancel(
            $fetcher,
            $this->createPaymentApiFactory($api),
            $this->createConfigurationProvider(),
            $this->createCancelRequestBuilder(),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(LoggerInterface::class)
        );

        $sut->cancel($this->createOrderEntity());
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

    private function createCancelRequestBuilder(): CancelRequest
    {
        return new CancelRequest(new FormatHelper());
    }
}
