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
    public function createPayment(string $data)
    {
        $this->setAuthorizationHeader();
        $this->client->setHeader('commercePlatformTag', $this->buildCommercePlatformTag());
        $url = $this->getCreatePaymentUrl();

        return $this->handleResponse($this->client->post($url, $data));
    }

    /**
     * @throws EasyApiException
     */
    public function getPayment(string $paymentId): Payment
    {
        $this->setAuthorizationHeader();
        $url = $this->getGetPaymentUrl($paymentId);

        return new Payment($this->handleResponse($this->client->get($url)));
    }

    public function updateReference(string $paymentId, string $data)
    {
        $this->setAuthorizationHeader();
        $url = $this->getUpdateReferenceUrl($paymentId);

        return $this->handleResponse($this->client->put($url, $data, true));
    }

    public function chargePayment(string $paymentId, string $data)
    {
        $this->setAuthorizationHeader();
        $url = $this->getChargePaymentUrl($paymentId);

        return $this->handleResponse($this->client->post($url, $data));
    }

    public function refundPayment(string $chargeId, string $data)
    {
        $this->setAuthorizationHeader();
        $url = $this->getRefundPaymentUrl($chargeId);

        return $this->handleResponse($this->client->post($url, $data));
    }

    public function voidPayment(string $paymentId, string $data = null): void
    {
        $this->setAuthorizationHeader();
        $url = $this->getVoidPaymentUrl($paymentId);
        $this->handleResponse($this->client->post($url, $data));
    }

    private function getUpdateReferenceUrl(string $paymentId): string
    {
        return $this->isLiveEnv()
            ? self::ENDPOINT_LIVE_PAYMENTS . $paymentId . '/referenceinformation'
            : self::ENDPOINT_TEST_PAYMENTS . $paymentId . '/referenceinformation';
    }

    private function getChargePaymentUrl(string $paymentId): string
    {
        return $this->isLiveEnv()
            ? self::ENDPOINT_LIVE_PAYMENTS . $paymentId . '/charges'
            : self::ENDPOINT_TEST_PAYMENTS . $paymentId . '/charges';
    }

    private function getVoidPaymentUrl(string $paymentId): string
    {
        return $this->isLiveEnv()
            ? self::ENDPOINT_LIVE_PAYMENTS . $paymentId . '/cancels'
            : self::ENDPOINT_TEST_PAYMENTS . $paymentId . '/cancels';
    }

    private function getRefundPaymentUrl(string $chargeId): string
    {
        return $this->isLiveEnv()
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

    private function setAuthorizationHeader(): void
    {
        $this->client->setHeader('Authorization', str_replace('-', '', trim($this->getAuthorizationKey())));
    }

    private function getCreatePaymentUrl(): string
    {
        return $this->isLiveEnv() ? self::ENDPOINT_LIVE_PAYMENTS : self::ENDPOINT_TEST_PAYMENTS;
    }

    private function getGetPaymentUrl(string $paymentId): string
    {
        return $this->isLiveEnv() ? self::ENDPOINT_LIVE_PAYMENTS . $paymentId : self::ENDPOINT_TEST_PAYMENTS . $paymentId;
    }

    private function getAuthorizationKey(): string
    {
        return (string) $this->configService->getSecretKey();
    }

    private function isLiveEnv(): bool
    {
        return self::ENV_LIVE === (string) $this->configService->getEnvironment();
    }

    private function buildCommercePlatformTag(): string
    {
        return sprintf('%s %s, %s, %s',
            'Shopware', // @todo const
            $this->shopVersion,
            NetsCheckout::PLUGIN_VERSION,
            PHP_VERSION
        );
    }
}
