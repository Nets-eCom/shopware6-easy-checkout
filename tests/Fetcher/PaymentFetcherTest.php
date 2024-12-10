<?php declare(strict_types=1);

namespace NexiNets\Tests\Fetcher;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePaymentResult;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Fetcher\PaymentFetcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

class PaymentFetcherTest extends TestCase
{
    private PaymentApiFactory|MockObject $paymentApiFactory;

    private ConfigurationProvider|MockObject $configurationProvider;

    private PaymentApi|MockObject $paymentApi;

    private CacheItemPoolInterface|MockObject $cache;

    protected function setUp(): void
    {
        $this->paymentApiFactory = $this->createMock(PaymentApiFactory::class);
        $this->configurationProvider = $this->createMock(ConfigurationProvider::class);
        $this->paymentApi = $this->createMock(PaymentApi::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
    }

    public function testFetchPayment(): void
    {
        $salesChannelId = 'test_sales_channel';
        $paymentId = 'test_payment_id';

        $this->configurationProvider
            ->method('getSecretKey')
            ->with($salesChannelId)
            ->willReturn('secret_key');

        $this->configurationProvider
            ->method('isLiveMode')
            ->with($salesChannelId)
            ->willReturn(true);

        $this->paymentApiFactory
            ->method('create')
            ->with('secret_key', true)
            ->willReturn($this->paymentApi);

        $paymentResult = $this->createPaymentResult();
        $this->paymentApi
            ->method('retrievePayment')
            ->with($paymentId)
            ->willReturn($paymentResult);

        $sut = new PaymentFetcher(
            $this->paymentApiFactory,
            $this->configurationProvider,
            $this->cache
        );
        $result = $sut->fetchPayment($salesChannelId, $paymentId);

        $this->assertSame($paymentResult->getPayment(), $result);
    }

    private function createPaymentResult(): RetrievePaymentResult
    {
        return RetrievePaymentResult::fromJson('{
            "payment": {
                "paymentId": "025400006091b1ef6937598058c4e487",
                "summary": {
                    "reservedAmount": 100
                },
                "consumer": {
                    "shippingAddress": {},
                    "billingAddress": {},
                    "privatePerson": {},
                    "company": {}
                },
                "paymentDetails": {},
                "orderDetails": {
                    "amount": 100,
                    "currency": "EUR"
                },
                "checkout": {
                    "url": "https://example.com/checkout",
                    "cancelUrl": null
                },
                "created": "2019-08-24T14:15:22Z",
                "refunds": [],
                "charges": []
            }
        }');
    }
}
