<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Api;

use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Http\HttpClient;
use NexiNets\CheckoutApi\Http\HttpClientException;
use NexiNets\CheckoutApi\Model\Request\Charge;
use NexiNets\CheckoutApi\Model\Request\Payment;
use NexiNets\CheckoutApi\Model\Result\ChargeResult;
use NexiNets\CheckoutApi\Model\Result\Payment\PaymentWithHostedCheckoutResult;
use NexiNets\CheckoutApi\Model\Result\RetrievePaymentResult;

class PaymentApi
{
    private const PAYMENTS_ENDPOINT = '/v1/payments';

    private const PAYMENT_CHARGES = '/charges';

    public function __construct(
        private readonly HttpClient $client,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @throws PaymentApiException
     */
    public function createPayment(Payment $payment): PaymentWithHostedCheckoutResult
    {
        try {
            $response = $this->client->post($this->getPaymentsUrl(), json_encode($payment));
        } catch (HttpClientException $httpClientException) {
            throw new PaymentApiException(
                'Couldn\'t create payment',
                $httpClientException->getCode(),
                $httpClientException
            );
        }

        $code = $response->getStatusCode();
        $contents = $response->getBody()->getContents();

        if (!$this->isSuccessCode($code)) {
            throw $this->createPaymentApiException($code, $contents);
        }

        return PaymentWithHostedCheckoutResult::fromJson($contents);
    }

    /**
     * @throws PaymentApiException
     */
    public function retrievePayment(string $paymentId): RetrievePaymentResult
    {
        try {
            $response = $this->client->get(\sprintf('%s/%s', $this->getPaymentsUrl(), $paymentId));
        } catch (HttpClientException $httpClientException) {
            throw new PaymentApiException(
                \sprintf('Couldn\'t retrieve payment for a given id: %s', $paymentId),
                $httpClientException->getCode(),
                $httpClientException
            );
        }

        $code = $response->getStatusCode();
        $contents = $response->getBody()->getContents();

        if (!$this->isSuccessCode($code)) {
            throw $this->createPaymentApiException($code, $contents);
        }

        return RetrievePaymentResult::fromJson($contents);
    }

    public function createCharge(string $paymentId, Charge $charge): ChargeResult
    {
        try {
            $response = $this->client->post($this->getPaymentChargesUrl($paymentId), json_encode($charge));
        } catch (HttpClientException $httpClientException) {
            throw new PaymentApiException(
                \sprintf('Couldn\'t create charge for a given payment with id: %s', $paymentId),
                $httpClientException->getCode(),
                $httpClientException
            );
        }

        $code = $response->getStatusCode();
        $contents = $response->getBody()->getContents();

        if (!$this->isSuccessCode($code)) {
            throw $this->createPaymentApiException($code, $contents);
        }

        return ChargeResult::fromJson($contents);
    }

    private function getPaymentChargesUrl(string $paymentId): string
    {
        return \sprintf('%s/%s%s', $this->getPaymentsUrl(), $paymentId, self::PAYMENT_CHARGES);
    }

    private function getPaymentsUrl(): string
    {
        return \sprintf('%s%s', $this->baseUrl, self::PAYMENTS_ENDPOINT);
    }

    private function isSuccessCode(int $code): bool
    {
        return $code >= 200 && $code < 300;
    }

    private function createPaymentApiException(int $code, string $contents): PaymentApiException
    {
        return match (true) {
            $code >= 300 && $code < 400 => new PaymentApiException('Redirection not supported'),
            $code >= 400 && $code < 500 => new PaymentApiException(\sprintf('Client error: %s', $contents)),
            $code >= 500 && $code < 600 => new PaymentApiException('Server error occurred'),
            default => new PaymentApiException('Unexpected status code'),
        };
    }
}
