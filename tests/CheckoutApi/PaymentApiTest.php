<?php

declare(strict_types=1);

namespace NexiNets\Tests\CheckoutApi;

use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Http\Configuration;
use NexiNets\CheckoutApi\Http\HttpClient;
use NexiNets\CheckoutApi\Model\Request\Payment;
use NexiNets\CheckoutApi\Model\Request\Payment\HostedCheckout;
use NexiNets\CheckoutApi\Model\Request\Payment\Item;
use NexiNets\CheckoutApi\Model\Request\Payment\Notification;
use NexiNets\CheckoutApi\Model\Request\Payment\Order;
use NexiNets\CheckoutApi\Model\Request\Payment\Webhook;
use NexiNets\CheckoutApi\Model\Result\Payment\PaymentWithHostedCheckoutResult;
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

        $streamFactory = $this->createStub(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturn($stream);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $sut = new PaymentApi(
            new HttpClient(
                $this->createPsrClient($response),
                $this->createRequestFactoryStub(),
                $streamFactory,
                new Configuration('1234')
            ),
            'https://api.example.com/'
        );

        $result = $sut->createPayment($this->createPaymentRequest());

        $this->assertInstanceOf(PaymentWithHostedCheckoutResult::class, $result);
        $this->assertSame('1234', $result->getPaymentId());
        $this->assertSame('https://api.example.com/hostedUrl', $result->getHostedPaymentPageUrl());
    }

    public function testItThrowsExceptionOnPsrClientException(): void
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

    public function testItThrowsExceptionOnUnsuccessfulStatusCode(): void
    {
        $this->expectException(PaymentApiException::class);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);

        $sut = new PaymentApi(
            new HttpClient(
                $this->createPsrClient($response),
                $this->createRequestFactoryStub(),
                $this->createStub(StreamFactoryInterface::class),
                new Configuration('1234')
            ),
            'https://api.example.com/'
        );

        $sut->createPayment($this->createPaymentRequest());
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
        return new class() implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw $this->createClientException();
            }

            private function createClientException(): ClientExceptionInterface
            {
                return new class() extends \Exception implements ClientExceptionInterface {};
            }
        };
    }

    public function createPaymentRequest(): Payment
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
                'https://api.example.com/returnUrl',
                'https://api.example.com/cancelUrl',
                'https://api.example.com/termsUrl',
            ),
            new Notification(
                [
                    new Webhook('event', 'https://shop.example.com', '1234'),
                ]
            )
        );
    }

    public function createRequestFactoryStub(): RequestFactoryInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('withBody')->willReturnSelf();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createStub(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        return $requestFactory;
    }
}
