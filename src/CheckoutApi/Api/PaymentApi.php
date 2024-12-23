<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Api;

use NexiNets\CheckoutApi\Api\Exception\ClientErrorPaymentApiException;
use NexiNets\CheckoutApi\Api\Exception\InternalErrorPaymentApiException;
use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Http\HttpClient;
use NexiNets\CheckoutApi\Http\HttpClientException;
use NexiNets\CheckoutApi\Model\Request\Cancel;
use NexiNets\CheckoutApi\Model\Request\Charge;
use NexiNets\CheckoutApi\Model\Request\MyReference;
use NexiNets\CheckoutApi\Model\Request\Payment;
use NexiNets\CheckoutApi\Model\Request\ReferenceInformation;
use NexiNets\CheckoutApi\Model\Request\RefundCharge;
use NexiNets\CheckoutApi\Model\Request\RefundPayment;
use NexiNets\CheckoutApi\Model\Request\UpdateOrder;
use NexiNets\CheckoutApi\Model\Result\ChargeResult;
use NexiNets\CheckoutApi\Model\Result\Payment\PaymentWithHostedCheckoutResult;
use NexiNets\CheckoutApi\Model\Result\RefundChargeResult;
use NexiNets\CheckoutApi\Model\Result\RefundPaymentResult;
use NexiNets\CheckoutApi\Model\Result\RetrievePaymentResult;

class PaymentApi
{
    private const PAYMENTS_ENDPOINT = '/v1/payments';

    private const PAYMENT_CHARGES = '/charges';

    private const PAYMENT_CANCELS = '/cancels';

    private const PAYMENT_UPDATE_REFERENCE_INFORMATION = '/referenceinformation';

    private const PAYMENT_UPDATE_MY_REFERENCE = '/myreference';

    private const PAYMENT_UPDATE_ORDER = '/orderitems';

    private const PAYMENT_TERMINATE = '/terminate';

    private const CHARGES_ENDPOINT = '/v1/charges';

    private const REFUNDS = '/refunds';

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

    /**
     * @throws PaymentApiException
     */
    public function cancel(string $paymentId, Cancel $cancel): void
    {
        try {
            $response = $this->client->post($this->getPaymentOperationEndpoint($paymentId, self::PAYMENT_CANCELS), json_encode($cancel));
        } catch (HttpClientException $httpClientException) {
            throw new PaymentApiException(
                \sprintf('Couldn\'t cancel for a given payment id: %s', $paymentId),
                $httpClientException->getCode(),
                $httpClientException
            );
        }

        $code = $response->getStatusCode();
        $contents = $response->getBody()->getContents();

        if (!$this->isSuccessCode($code)) {
            throw $this->createPaymentApiException($code, $contents);
        }
    }

    public function updateReferenceInformation(string $paymentId, ReferenceInformation $referenceInformation): void
    {
        try {
            $response = $this->client->put(
                $this->getPaymentOperationEndpoint($paymentId, self::PAYMENT_UPDATE_REFERENCE_INFORMATION),
                json_encode($referenceInformation)
            );
        } catch (HttpClientException $httpClientException) {
            throw new PaymentApiException(
                \sprintf('Couldn\'t update reference information for a given payment id: %s', $paymentId),
                $httpClientException->getCode(),
                $httpClientException
            );
        }

        $code = $response->getStatusCode();
        $contents = $response->getBody()->getContents();

        if (!$this->isSuccessCode($code)) {
            throw $this->createPaymentApiException($code, $contents);
        }
    }

    /**
     * @throws PaymentApiException
     * @throws \JsonException
     */
    public function updateMyReference(string $paymentId, MyReference $myReference): void
    {
        try {
            $response = $this->client->put(
                $this->getPaymentOperationEndpoint($paymentId, self::PAYMENT_UPDATE_MY_REFERENCE),
                json_encode($myReference)
            );
        } catch (HttpClientException $httpClientException) {
            throw new PaymentApiException(
                \sprintf('Couldn\'t update myReference information for a given payment id: %s', $paymentId),
                $httpClientException->getCode(),
                $httpClientException
            );
        }

        $code = $response->getStatusCode();

        if (!$this->isSuccessCode($code)) {
            throw $this->createPaymentApiException($code, $response->getBody()->getContents());
        }
    }

    /**
     * @throws PaymentApiException
     * @throws \JsonException
     */
    public function updatePaymentOrder(
        string $paymentId,
        UpdateOrder $updateOrder
    ): void {
        try {
            $response = $this->client->put(
                $this->getPaymentOperationEndpoint($paymentId, self::PAYMENT_UPDATE_ORDER),
                json_encode($updateOrder)
            );
        } catch (HttpClientException $httpClientException) {
            throw new PaymentApiException(
                \sprintf('Couldn\'t update payment order for a given payment id: %s', $paymentId),
                $httpClientException->getCode(),
                $httpClientException
            );
        }

        $code = $response->getStatusCode();

        if (!$this->isSuccessCode($code)) {
            throw $this->createPaymentApiException($code, $response->getBody()->getContents());
        }
    }

    /**
     * @throws PaymentApiException
     */
    public function terminate(string $paymentId): void
    {
        try {
            $response = $this->client->put(
                $this->getPaymentOperationEndpoint($paymentId, self::PAYMENT_TERMINATE),
                ''
            );
        } catch (HttpClientException $httpClientException) {
            throw new PaymentApiException(
                \sprintf('Couldn\'t terminate payment id: %s', $paymentId),
                $httpClientException->getCode(),
                $httpClientException
            );
        }

        $code = $response->getStatusCode();

        if (!$this->isSuccessCode($code)) {
            throw $this->createPaymentApiException($code, $response->getBody()->getContents());
        }
    }

    public function charge(string $paymentId, Charge $charge): ChargeResult
    {
        try {
            $response = $this->client->post($this->getPaymentOperationEndpoint($paymentId, self::PAYMENT_CHARGES), json_encode($charge));
        } catch (HttpClientException $httpClientException) {
            throw new PaymentApiException(
                \sprintf('Couldn\'t create charge for a given payment id: %s', $paymentId),
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

    /**
     * @throws PaymentApiException
     */
    public function refundCharge(string $chargeId, RefundCharge $refund): RefundChargeResult
    {
        try {
            $response = $this->client->post(
                $this->getChargesOperationEndpoint($chargeId, self::REFUNDS),
                json_encode($refund)
            );
        } catch (HttpClientException $httpClientException) {
            throw new PaymentApiException(
                \sprintf('Couldn\'t refund charge with id: %s', $chargeId),
                $httpClientException->getCode(),
                $httpClientException
            );
        }

        $code = $response->getStatusCode();
        $contents = $response->getBody()->getContents();

        if (!$this->isSuccessCode($code)) {
            throw $this->createPaymentApiException($code, $contents);
        }

        return RefundChargeResult::fromJson($contents);
    }

    /**
     * @throws PaymentApiException
     * @throws \JsonException
     */
    public function refundPayment(string $paymentId, RefundPayment $refundPayment): RefundPaymentResult
    {
        try {
            $response = $this->client->post(
                $this->getPaymentOperationEndpoint($paymentId, self::REFUNDS),
                json_encode($refundPayment)
            );
        } catch (HttpClientException $httpClientException) {
            throw new PaymentApiException(
                \sprintf('Couldn\'t refund payment with id: %s', $paymentId),
                $httpClientException->getCode(),
                $httpClientException
            );
        }

        $code = $response->getStatusCode();
        $contents = $response->getBody()->getContents();

        if (!$this->isSuccessCode($code)) {
            throw $this->createPaymentApiException($code, $contents);
        }

        return RefundPaymentResult::fromJson($contents);
    }

    private function getPaymentOperationEndpoint(string $paymentId, string $operation): string
    {
        return \sprintf('%s/%s%s', $this->getPaymentsUrl(), $paymentId, $operation);
    }

    private function getPaymentsUrl(): string
    {
        return \sprintf('%s%s', $this->baseUrl, self::PAYMENTS_ENDPOINT);
    }

    private function getChargesOperationEndpoint(string $paymentId, string $operation): string
    {
        return \sprintf('%s/%s%s', $this->getChargesUrl(), $paymentId, $operation);
    }

    private function getChargesUrl(): string
    {
        return \sprintf('%s%s', $this->baseUrl, self::CHARGES_ENDPOINT);
    }

    private function isSuccessCode(int $code): bool
    {
        return $code >= 200 && $code < 300;
    }

    /**
     * @throws \JsonException
     */
    private function createPaymentApiException(int $code, string $contents): PaymentApiException
    {
        return match (true) {
            $code >= 300 && $code < 400 => new PaymentApiException('Redirection not supported'),
            $code === 400 => new ClientErrorPaymentApiException(\sprintf('Client error: %s', $contents), $contents),
            $code === 401 => new PaymentApiException(\sprintf('Unauthorized: %s', $contents)),
            $code === 404 => new PaymentApiException(\sprintf('Client error: %s', $contents)),
            $code >= 402 && $code < 500 => new InternalErrorPaymentApiException($contents),
            $code >= 500 && $code < 600 => new PaymentApiException(\sprintf('Server error occurred: %s', $contents)),
            default => new PaymentApiException(\sprintf('Unexpected status code: %d', $code)),
        };
    }
}
