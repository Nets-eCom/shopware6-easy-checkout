<?php

namespace Nets\Checkout\Service\Easy\Api;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Psr\Http\Message\ResponseInterface;

/**
 * Description of Client
 */
class Client
{
    private GuzzleClient $client;

    private array $headers = [];

    public function __construct()
    {
        $this->init();
    }

    /**
     * @throws EasyApiException
     */
    public function post(string $url, string|array $data = []): ResponseInterface
    {
        try {
            $params = ['headers' => $this->headers,
                       'body'    => $data];

            return $this->client->request('POST', $url, $params);
        } catch (ClientException $ex) {
            throw new EasyApiException($ex->getResponse()->getBody(), $ex->getCode());
        } catch (GuzzleException $ex) {
            throw new EasyApiException($ex->getMessage(), $ex->getCode());
        }
    }

    public function setHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    public function isSuccess()
    {
        return $this->client->isSuccess();
    }

    public function getResponse()
    {
        return $this->client->getResponse();
    }

    /**
     * @throws EasyApiException
     */
    public function get(string $url): ResponseInterface
    {
        try {
            $params = ['headers' => $this->headers];

            return $this->client->request('GET', $url, $params);
        } catch (GuzzleException $ex) {
            throw new EasyApiException($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @throws EasyApiException
     */
    public function put($url, $data = []): ResponseInterface
    {
        try {
            $params = ['headers' => $this->headers,
                'body'           => $data];

            return $this->client->request('PUT', $url, $params);
        } catch (ClientException $ex) {
            throw new EasyApiException($ex->getResponse()->getBody(), $ex->getCode());
        } catch (GuzzleException $ex) {
            throw new EasyApiException($ex->getMessage(), $ex->getCode());
        }
    }

    public function getHttpStatus()
    {
        return $this->client->getHttpStatus();
    }

    public function getErrorCode()
    {
        return $this->client->getErrorCode();
    }

    public function getErrorMessage()
    {
        return $this->client->getErrorMessage();
    }

    protected function init(): void
    {
        $params = ['headers' => ['Content-Type' => 'text/json',
             'Accept'                           => 'test/json']];
        $this->client = new GuzzleClient($params);
    }
}
