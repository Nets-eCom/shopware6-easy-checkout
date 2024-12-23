<?php

declare(strict_types=1);

namespace NexiNets\Tests\CheckoutApi;

use NexiNets\CheckoutApi\Api\Exception\ClientErrorPaymentApiException;
use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Http\Configuration;
use NexiNets\CheckoutApi\Http\HttpClient;
use NexiNets\CheckoutApi\Model\Request\Charge;
use NexiNets\CheckoutApi\Model\Request\FullCharge;
use NexiNets\CheckoutApi\Model\Request\FullRefundCharge;
use NexiNets\CheckoutApi\Model\Request\Item;
use NexiNets\CheckoutApi\Model\Request\MyReference;
use NexiNets\CheckoutApi\Model\Request\Payment;
use NexiNets\CheckoutApi\Model\Request\Payment\HostedCheckout;
use NexiNets\CheckoutApi\Model\Request\Payment\Notification;
use NexiNets\CheckoutApi\Model\Request\Payment\Order;
use NexiNets\CheckoutApi\Model\Request\Payment\Webhook;
use NexiNets\CheckoutApi\Model\Request\ReferenceInformation;
use NexiNets\CheckoutApi\Model\Request\RefundCharge;
use NexiNets\CheckoutApi\Model\Request\RefundPayment;
use NexiNets\CheckoutApi\Model\Request\UpdateOrder;
use NexiNets\CheckoutApi\Model\Request\UpdateOrder\PaymentMethod;
use NexiNets\CheckoutApi\Model\Request\UpdateOrder\Shipping;
use NexiNets\CheckoutApi\Model\Result\ChargeResult;
use NexiNets\CheckoutApi\Model\Result\Payment\PaymentWithHostedCheckoutResult;
use NexiNets\CheckoutApi\Model\Result\RefundChargeResult;
use NexiNets\CheckoutApi\Model\Result\RefundPaymentResult;
use NexiNets\CheckoutApi\Model\Result\RetrievePaymentResult;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class PaymentApiTest extends TestCase
{
    public function testItCreatesPayment(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream
            ->method('getContents')
            ->willReturn(
                json_encode([
                    'paymentId' => '1234',
                    'hostedPaymentPageUrl' => 'https://api.example.com/hostedUrl',
                ])
            );

        $streamFactory = $this->createStreamFactory($stream);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $sut = $this->createPaymentApi($response, $streamFactory);

        $result = $sut->createPayment($this->createPaymentRequest());

        $this->assertInstanceOf(PaymentWithHostedCheckoutResult::class, $result);
        $this->assertSame('1234', $result->getPaymentId());
        $this->assertSame('https://api.example.com/hostedUrl', $result->getHostedPaymentPageUrl());
    }

    public function testItThrowsExceptionOnPsrClientExceptionCreatePayment(): void
    {
        $this->expectException(PaymentApiException::class);

        $sut = new PaymentApi(
            new HttpClient(
                $this->createPsrClientThrowingException(),
                $this->createRequestFactoryStub(),
                $this->createStub(StreamFactoryInterface::class),
                new Configuration('1234')
            ),
            'https://api.example.com/'
        );

        $sut->createPayment($this->createPaymentRequest());
    }

    public function testItThrowsExceptionOnClientErrorCreatePayment(): void
    {
        $this->expectException(ClientErrorPaymentApiException::class);

        $stream = $this->createStub(StreamInterface::class);
        $stream
            ->method('getContents')
            ->willReturn('{
                "errors": {
                    "property1": [
                        "string"
                    ],
                    "property2": [
                        "string"
                    ]
                }
            }');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $response->method('getBody')->willReturn($stream);

        $sut = $this->createPaymentApi($response, $this->createStreamFactory($stream));
        $sut->createPayment($this->createPaymentRequest());
    }

    public function testItThrowsExceptionOnUnsuccessfulCreatePayment(): void
    {
        $this->expectException(PaymentApiException::class);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);

        $sut = $this->createPaymentApi($response, $this->createStub(StreamFactoryInterface::class));
        $sut->createPayment($this->createPaymentRequest());
    }

    public function testItRetrievesPayment(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream
            ->method('getContents')
            ->willReturn(
                json_encode([
                    'payment' => [
                        'paymentId' => '1234',
                        'orderDetails' => [
                            'amount' => 1000,
                            'currency' => 'PLN',
                        ],
                        'created' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                        'consumer' => [
                            'shippingAddress' => [],
                            'billingAddress' => [],
                            'privatePerson' => [],
                            'company' => [],
                        ],
                        'summary' => [],
                        'paymentDetails' => [
                            'invoiceDetails' => [],
                        ],
                        'checkout' => [
                            'url' => 'https://shop.example.com/checkout/1000',
                            'cancelUrl' => 'https://shop.example.com/cancelUrl',
                        ],
                    ],
                ])
            );

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $sut = $this->createPaymentApi($response, $this->createStreamFactory($stream));

        $result = $sut->retrievePayment('1234');

        $this->assertInstanceOf(RetrievePaymentResult::class, $result);
        $this->assertSame($result->getPayment()->getPaymentId(), '1234');
    }

    public function testItThrowsExceptionOnUnknownPaymentRetrieve(): void
    {
        $this->expectException(PaymentApiException::class);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $sut = $this->createPaymentApi($response, $this->createStub(StreamFactoryInterface::class));

        $sut->retrievePayment('1234');
    }

    public function testItCreatesCharge(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream
            ->method('getContents')
            ->willReturn(
                json_encode([
                    'chargeId' => '1234',
                ])
            );

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $sut = $this->createPaymentApi($response, $this->createStreamFactory($stream));

        $result = $sut->charge('1234', $this->createChargeRequest());

        $this->assertInstanceOf(ChargeResult::class, $result);
        $this->assertSame('1234', $result->getChargeId());
    }

    public function testItUpdatesReferenceInformation(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream
            ->method('getContents')
            ->willReturn('');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getStatusCode')->willReturn(200);
        $response->expects($this->once())->method('getBody')->willReturn($stream);

        $sut = $this->createPaymentApi($response, $this->createStreamFactory($stream));

        $sut->updateReferenceInformation('1234', $this->createReferenceInformationRequest());
    }

    public function testItUpdatesMyReferenceInformation(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream
            ->method('getContents')
            ->willReturn('');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getStatusCode')->willReturn(204);

        $sut = $this->createPaymentApi($response, $this->createStreamFactory($stream));

        $sut->updateMyReference('1234', $this->createMyReferenceRequest());
    }

    public function testItUpdatesOrder(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream
            ->method('getContents')
            ->willReturn('');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getStatusCode')->willReturn(204);

        $sut = $this->createPaymentApi($response, $this->createStreamFactory($stream));
        $sut->updatePaymentOrder('1234', $this->createUpdateOrderRequest());
    }

    public function testItTerminatesPayment(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream
            ->method('getContents')
            ->willReturn('');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getStatusCode')->willReturn(204);

        $sut = $this->createPaymentApi($response, $this->createStreamFactory($stream));

        $sut->terminate('1234');
    }

    public function testItRefundsCharge(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream
            ->method('getContents')
            ->willReturn(
                json_encode([
                    'refundId' => '1234',
                ])
            );

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $sut = $this->createPaymentApi($response, $this->createStreamFactory($stream));

        $result = $sut->refundCharge('1234', $this->createRefundChargeRequest());

        $this->assertInstanceOf(RefundChargeResult::class, $result);
        $this->assertSame('1234', $result->getRefundId());
    }

    public function testItRefundsPayment(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream
            ->method('getContents')
            ->willReturn(
                json_encode([
                    'refundId' => '1234',
                ])
            );

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $sut = $this->createPaymentApi($response, $this->createStreamFactory($stream));

        $result = $sut->refundPayment('1234', $this->createRefundPaymentRequest());

        $this->assertInstanceOf(RefundPaymentResult::class, $result);
        $this->assertSame('1234', $result->getRefundId());
    }

    private function createPsrClient(ResponseInterface $response): ClientInterface
    {
        return new class($response) implements ClientInterface {
            public function __construct(private readonly ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function createPsrClientThrowingException(): ClientInterface
    {
        return new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw $this->createClientException();
            }

            private function createClientException(): ClientExceptionInterface
            {
                return new class extends \Exception implements ClientExceptionInterface {};
            }
        };
    }

    private function createPaymentRequest(): Payment
    {
        return new Payment(
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
            ),
            new Notification(
                [
                    new Webhook('event', 'https://shop.example.com', '1234'),
                ]
            )
        );
    }

    private function createChargeRequest(): Charge
    {
        return new FullCharge(1);
    }

    private function createReferenceInformationRequest(): ReferenceInformation
    {
        return new ReferenceInformation('https://shop.example.com/checkout/1000', 'ref1234');
    }

    private function createMyReferenceRequest(): MyReference
    {
        return new MyReference('foo');
    }

    private function createRefundChargeRequest(): RefundCharge
    {
        return new FullRefundCharge(1);
    }

    private function createUpdateOrderRequest(): UpdateOrder
    {
        $item = new Item('bar', 1, 'pcs', 1, 1, 1, 'foo');

        return new UpdateOrder(
            1,
            [$item],
            new Shipping(false),
            [
                new PaymentMethod(
                    'foo',
                    $item
                ),
            ]
        );
    }

    private function createRefundPaymentRequest(): RefundPayment
    {
        return new RefundPayment(1);
    }

    private function createRequestFactoryStub(): RequestFactoryInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('withBody')->willReturnSelf();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createStub(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        return $requestFactory;
    }

    private function createPaymentApi(ResponseInterface $response, StreamFactoryInterface $streamFactory): PaymentApi
    {
        return new PaymentApi(
            new HttpClient(
                $this->createPsrClient($response),
                $this->createRequestFactoryStub(),
                $streamFactory,
                new Configuration('1234')
            ),
            'https://api.example.com/'
        );
    }

    private function createStreamFactory(StreamInterface $stream): StreamFactoryInterface
    {
        $streamFactory = $this->createStub(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturn($stream);

        return $streamFactory;
    }
}
