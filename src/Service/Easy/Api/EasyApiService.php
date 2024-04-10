<?php

namespace Nets\Checkout\Service\Easy\Api;

use Nets\Checkout\NetsCheckout;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\ConfigService;

class EasyApiService
{
    public const ENV_LIVE = 'live';

    public const ENV_TEST = 'test';

    private const LIVE_URL = 'https://api.dibspayment.eu';
    private const TEST_URL = 'https://test.api.dibspayment.eu';
    private const ENDPOINT_LIVE_PAYMENTS = self::LIVE_URL . '/v1/payments/';
    private const ENDPOINT_TEST_PAYMENTS = self::TEST_URL . '/v1/payments/';
    private const ENDPOINT_LIVE_CHARGES = self::LIVE_URL . '/v1/charges/';
    private const ENDPOINT_TEST_CHARGES = self::TEST_URL . '/v1/charges/';

    private Client $client;
    private ConfigService $configService;
    private string $shopVersion;

    public function __construct(
        Client $client,
        ConfigService $configService,
        string $shopVersion
    ) {
        $this->client = $client;
        $this->configService = $configService;
        $this->shopVersion = $shopVersion;
    }

    /**
     * @throws EasyApiException
     */
    public function createPayment(string $data, ?string $salesChannelId = null): ?string
    {
        $this->setAuthorizationHeader(null, $salesChannelId);
        $this->client->setHeader('commercePlatformTag', $this->buildCommercePlatformTag());
        $url = $this->getCreatePaymentUrl($salesChannelId);

        return $this->handleResponse($this->client->post($url, $data));
    }

    /**
     * @throws EasyApiException
     */
    public function getPayment(string $paymentId, ?string $salesChannelId = null): Payment
    {
        $this->setAuthorizationHeader(null, $salesChannelId);
        $url = $this->getGetPaymentUrl($paymentId, $salesChannelId);

        return new Payment($this->handleResponse($this->client->get($url)));
    }

    public function updateReference(string $paymentId, string $data, ?string $salesChannelId = null)
    {
        $this->setAuthorizationHeader(null, $salesChannelId);
        $url = $this->getUpdateReferenceUrl($paymentId, $salesChannelId);

        return $this->handleResponse($this->client->put($url, $data, true));
    }

    public function chargePayment(string $paymentId, string $data, ?string $salesChannelId = null)
    {
        $this->setAuthorizationHeader(null, $salesChannelId);
        $url = $this->getChargePaymentUrl($paymentId, $salesChannelId);

        return $this->handleResponse($this->client->post($url, $data));
    }

    public function refundPayment(string $chargeId, string $data, ?string $salesChannelId = null)
    {
        $this->setAuthorizationHeader(null, $salesChannelId);
        $url = $this->getRefundPaymentUrl($chargeId, $salesChannelId);

        return $this->handleResponse($this->client->post($url, $data));
    }

    public function voidPayment(string $paymentId, string $data = null, ?string $salesChannelId = null): void
    {
        $this->setAuthorizationHeader(null, $salesChannelId);
        $url = $this->getVoidPaymentUrl($paymentId, $salesChannelId);
        $this->handleResponse($this->client->post($url, $data));
    }

    /**
     * @throws EasyApiException
     * @todo call termiante after verify
     */
    public function verifyConnection(string $env, string $secretKey, string $data): bool
    {
        $this->setAuthorizationHeader($secretKey);
        $url = $env === self::ENV_LIVE ? self::ENDPOINT_LIVE_PAYMENTS : self::ENDPOINT_TEST_PAYMENTS;

        try {
            $result = $this->handleResponse($this->client->post($url, $data));
        } catch (EasyApiException $e) {
            return false;
        }

        if (empty($result)) {
            return false;
        }

        $response = json_decode($result, true);
        if (empty($response['paymentId'])) {
            return false;
        }

        return true;
    }

    private function getUpdateReferenceUrl(string $paymentId, ?string $salesChannelId = null): string
    {
        return $this->isLiveEnv($salesChannelId)
            ? self::ENDPOINT_LIVE_PAYMENTS . $paymentId . '/referenceinformation'
            : self::ENDPOINT_TEST_PAYMENTS . $paymentId . '/referenceinformation';
    }

    private function getChargePaymentUrl(string $paymentId, ?string $salesChannelId = null): string
    {
        return $this->isLiveEnv($salesChannelId)
            ? self::ENDPOINT_LIVE_PAYMENTS . $paymentId . '/charges'
            : self::ENDPOINT_TEST_PAYMENTS . $paymentId . '/charges';
    }

    private function getVoidPaymentUrl(string $paymentId, ?string $salesChannelId = null): string
    {
        return $this->isLiveEnv($salesChannelId)
            ? self::ENDPOINT_LIVE_PAYMENTS . $paymentId . '/cancels'
            : self::ENDPOINT_TEST_PAYMENTS . $paymentId . '/cancels';
    }

    private function getRefundPaymentUrl(string $chargeId, ?string $salesChannelId = null): string
    {
        return $this->isLiveEnv($salesChannelId)
            ? self::ENDPOINT_LIVE_CHARGES . $chargeId . '/refunds'
            : self::ENDPOINT_TEST_CHARGES . $chargeId . '/refunds';
    }

    private function handleResponse($response)
    {
        $statusCode = $response->getStatusCode();

        // @todo handle different status codes
        if ($statusCode == 200 || $statusCode == 201) {
            return (string) $response->getBody();
        }
    }

    private function setAuthorizationHeader(string $authorizationKey = null, ?string $salesChannelId = null): void
    {
        if (null === $authorizationKey) {
            $authorizationKey = $this->getAuthorizationKey($salesChannelId);
        }

        $this->client->setHeader('Authorization', str_replace('-', '', trim($authorizationKey)));
    }

    private function getCreatePaymentUrl(?string $salesChannelId = null): string
    {
        return $this->isLiveEnv($salesChannelId) ? self::ENDPOINT_LIVE_PAYMENTS : self::ENDPOINT_TEST_PAYMENTS;
    }

    private function getGetPaymentUrl(string $paymentId, ?string $salesChannelId = null): string
    {
        return $this->isLiveEnv($salesChannelId) ? self::ENDPOINT_LIVE_PAYMENTS . $paymentId : self::ENDPOINT_TEST_PAYMENTS . $paymentId;
    }

    private function getAuthorizationKey(?string $salesChannelId = null): string
    {
        return (string)$this->configService->getSecretKey($salesChannelId);
    }

    private function isLiveEnv(?string $salesChannelId = null): bool
    {
        return self::ENV_LIVE === (string)$this->configService->getEnvironment($salesChannelId);
    }

    private function buildCommercePlatformTag(): string
    {
        return sprintf('%s %s, %s, php%s',
            'Shopware', // @todo const
            $this->shopVersion,
            NetsCheckout::PLUGIN_VERSION,
            PHP_VERSION
        );
    }
}
