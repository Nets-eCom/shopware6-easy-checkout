<?php
namespace Nets\Checkout\Service\Easy\Api;

use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;

/**
 * Description of EasyApiService
 *
 * @author mabe
 */
class EasyApiService
{
    public const ENDPOINT_TEST = 'https://test.api.dibspayment.eu/v1/payments/';

    public const ENDPOINT_LIVE = 'https://api.dibspayment.eu/v1/payments/';

    public const ENDPOINT_TEST_CHARGES = 'https://test.api.dibspayment.eu/v1/charges/';

    public const ENDPOINT_LIVE_CHARGES = 'https://api.dibspayment.eu/v1/charges/';

    public const ENV_LIVE = 'live';

    public const ENV_TEST = 'test';

    public const CUSTOM_API = 'https://reporting.sokoni.it/enquiry';

    private Client $client;

    private string $env;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->setEnv(self::ENV_LIVE);
    }

    public function setEnv(string $env = self::ENV_LIVE): void
    {
        $this->env = $env;
    }

    public function getEnv()
    {
        return $this->env;
    }

    public function setAuthorizationKey($key): void
    {
        $this->client->setHeader('Authorization', str_replace('-', '', trim($key)));
    }

    /**
     * @throws EasyApiException
     */
    public function createPayment(string $data)
    {
        $this->client->setHeader('commercePlatformTag', 'Shopware6');
        $url = $this->getCreatePaymentUrl();

        try {
            return $this->handleResponse($this->client->post($url, $data));
        } catch (EasyApiException $e) {
            return;
        }
    }

    /**
     * @throws EasyApiException
     */
    public function getPayment(string $paymentId): Payment
    {
        $url = $this->getGetPaymentUrl($paymentId);

        return new Payment($this->handleResponse($this->client->get($url)));
    }

    public function updateReference(string $paymentId, string $data)
    {
        $url = $this->getUpdateReferenceUrl($paymentId);

        return $this->handleResponse($this->client->put($url, $data, true));
    }

    public function chargePayment(string $paymentId, string $data)
    {
        $url = $this->getChargePaymentUrl($paymentId);

        return $this->handleResponse($this->client->post($url, $data));
    }

    public function refundPayment(string $chargeId, string $data)
    {
        $url = $this->getRefundPaymentUrl($chargeId);

        return $this->handleResponse($this->client->post($url, $data));
    }

    public function voidPayment(string $paymentId, string $data = null): void
    {
        $url = $this->getVoidPaymentUrl($paymentId);
        $this->handleResponse($this->client->post($url, $data));
    }

    public function getPluginVersion($data)
    {
        $this->client->setHeader('Content-Type', 'application/json');
        $this->client->setHeader('Accept', 'application/json');

        return $this->handleResponse($this->client->post(self::CUSTOM_API, $data));
    }

    public function getUpdateReferenceUrl(string $paymentId)
    {
        return ($this->getEnv() == self::ENV_LIVE) ? self::ENDPOINT_LIVE . $paymentId . '/referenceinformation' : self::ENDPOINT_TEST . $paymentId . '/referenceinformation';
    }

    public function getChargePaymentUrl(string $paymentId)
    {
        return ($this->getEnv() == self::ENV_LIVE) ? self::ENDPOINT_LIVE . $paymentId . '/charges' : self::ENDPOINT_TEST . $paymentId . '/charges';
    }

    public function getVoidPaymentUrl(string $paymentId)
    {
        return ($this->getEnv() == self::ENV_LIVE) ? self::ENDPOINT_LIVE . $paymentId . '/cancels' : self::ENDPOINT_TEST . $paymentId . '/cancels';
    }

    public function getRefundPaymentUrl(string $chargeId)
    {
        return ($this->getEnv() == self::ENV_LIVE) ? self::ENDPOINT_LIVE_CHARGES . $chargeId . '/refunds' : self::ENDPOINT_TEST_CHARGES . $chargeId . '/refunds';
    }

    protected function handleResponse($response)
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode == 200 || $statusCode == 201) {
            return (string) $response->getBody();
        }
    }

    protected function getCreatePaymentUrl()
    {
        return ($this->getEnv() == self::ENV_LIVE) ? self::ENDPOINT_LIVE : self::ENDPOINT_TEST;
    }

    protected function getGetPaymentUrl(string $paymentId)
    {
        return ($this->getEnv() == self::ENV_LIVE) ? self::ENDPOINT_LIVE . $paymentId : self::ENDPOINT_TEST . $paymentId;
    }
}
