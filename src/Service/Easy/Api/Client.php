<?php

namespace Nets\Checkout\Service\Easy\Api;

use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
/**
 * Description of Client
 *
 * @author mabe
 */
class Client {

    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * @var array
     */
    private $headers = [];

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
        }catch (\GuzzleHttp\Exception\ClientException $ex) {
            throw new EasyApiException($ex->getResponse()->getBody(), $ex->getCode());
        }catch(\GuzzleHttp\Exception\GuzzleException $ex) {
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
     * @return \Psr\Http\Message\ResponseInterface
     * @throws EasyApiException
     */
    public function get($url, $data = array()) {
        try {
            $params = ['headers' => $this->headers];
            return $this->client->request('GET', $url, $params);
        }catch(\GuzzleHttp\Exception\GuzzleException $ex) {
            throw new EasyApiException($ex->getMessage(), $ex->getCode());
        }
    }

    public function put($url, $data = array(), $payload = false) {
        return $this->client->put($url, $data, $payload);
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
