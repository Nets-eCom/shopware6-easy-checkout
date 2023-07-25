<?php

namespace Nets\Checkout\Service\Easy\Api;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
/**
 * Description of Client
 */
class Client {

    private \GuzzleHttp\Client $client;

    private array $headers = [];

    public function __construct() {
        $this->init();
    }

    protected function init() {
        $params = ['headers' =>
            ['Content-Type' => 'text/json',
             'Accept' => 'test/json']];
        $this->client = new \GuzzleHttp\Client($params);
    }

    /**
     * @param string $url
     * @param array $data
     * @return mixed
     * @throws EasyApiException
     */
    public function post($url, $data = array()) {
        try {
            $params = ['headers' => $this->headers,
                       'body' => $data];
            return $this->client->request('POST', $url, $params);
        }catch (ClientException $ex) { 
            throw new EasyApiException($ex->getResponse()->getBody(), $ex->getCode());
        }catch(GuzzleException $ex) {
            throw new EasyApiException($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function setHeader($key, $value) {
        $this->headers[$key] = $value;
    }

    public function isSuccess() {
        return $this->client->isSuccess();
    }

    public function getResponse() {
       return $this->client->getResponse();
    }

    /**
     * @param string $url
     * @param array $data
     * @return ResponseInterface
     * @throws EasyApiException
     */
    public function get($url, $data = array()) {
        try {
            $params = ['headers' => $this->headers];
            return $this->client->request('GET', $url, $params);
        }catch(GuzzleException $ex) {
            throw new EasyApiException($ex->getMessage(), $ex->getCode());
        }
    }

    public function put($url, $data = array(), $payload = false) {
        try {
            $params = ['headers' => $this->headers,
                'body' => $data];
            return $this->client->request('PUT', $url, $params);
        }catch (ClientException $ex) {
            throw new EasyApiException($ex->getResponse()->getBody(), $ex->getCode());
        }catch(GuzzleException $ex) {
            throw new EasyApiException($ex->getMessage(), $ex->getCode());
        }
    }

    public function getHttpStatus() {
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
}
