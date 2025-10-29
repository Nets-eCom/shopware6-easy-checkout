<?php declare(strict_types=1);

namespace Nexi\Checkout\Tests\Order;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Fetcher\CachablePaymentFetcherInterface;
use Nexi\Checkout\Order\OrderReferenceUpdate;
use Nexi\Checkout\RequestBuilder\ReferenceInformationRequest;
use Nexi\Checkout\Tests\Fixture\RetrievePaymentResultFixture;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Request\ReferenceInformation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class OrderReferenceUpdateTest extends TestCase
{
    public function testItSendUpdateReferenceInformation(): void
    {
        $api = $this->createMock(PaymentApi::class);
        $api
            ->expects($this->once())
            ->method('updateReferenceInformation')
            ->with(
                '025400006091b1ef6937598058c4e487',
                new ReferenceInformation('https://example.com/checkout', '1001')
            );

        $fetcher = $this->createMock(CachablePaymentFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('getCachedPayment')
            ->with('test_sales_channel_id', '025400006091b1ef6937598058c4e487')
            ->willReturn(RetrievePaymentResultFixture::reserved()->getPayment());


        $sut = new OrderReferenceUpdate(
            $fetcher,
            $this->createPaymentApiFactory($api),
            $this->createConfigurationProvider(),
            $this->createReferenceInformationRequestBuilder(),
            $this->createMock(LoggerInterface::class),
            $this->createMock(EventDispatcherInterface::class)
        );

        $sut->updateReferenceForTransaction($this->createOrderTransactionEntity());
    }

    private function createOrderTransactionEntity(): OrderTransactionEntity
    {
        $order = new OrderEntity();
        $order->setId('order_uuid');
        $order->setSalesChannelId('test_sales_channel_id');
        $order->setOrderNumber('1001');

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction_uuid');
        $transaction->setOrder($order);
        $transaction->setAmount(
            new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection([]), 1)
        );
        $transaction->setCustomFields([
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID => '025400006091b1ef6937598058c4e487',
        ]);

        return $transaction;
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

    private function createReferenceInformationRequestBuilder(): ReferenceInformationRequest
    {
        return new ReferenceInformationRequest();
    }
}
