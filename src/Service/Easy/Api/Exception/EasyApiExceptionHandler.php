<?php

namespace Nets\Checkout\Service\Easy\Api\Exception;

use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Description of EasyExceptionHandler
 *
 * @author mabe
 */
class EasyApiExceptionHandler
{
    /**
     * file for logging errors
     */
    public const LOG_FILE_NAME = 'nets-easy-log.log';

    private LoggerInterface $logger;

    private KernelInterface $kernel;

    public function __construct(LoggerInterface $logger, KernelInterface $kernel)
    {
        $this->logger = $logger;
        $this->kernel = $kernel;
    }

    /**
     * @param \Nets\Checkout\Service\Easy\Api\Exception\EasyApiException $e
     *
     * @throws \Exception
     */
    public function handle(EasyApiException $e, array $add = null): string
    {
        $prefixMessage = 'Exception call to Easy Api. ' . PHP_EOL;
        $stackTrace    = 'Stack trace: ' . PHP_EOL . $e->getTraceAsString();
        $message       = 'Response code:  ' . $e->getCode() . PHP_EOL . 'Message: ';

        switch ($e->getCode()) {
            case 400:
                $message .= 'Bad request: ' . $e->getMessage();

                break;
            case 401:
                $message .= 'Unauthorized access. Try to check Easy secret/live key';

                break;
            case 402:
                $message .= 'Payment required';

                break;
            case 404:
                $message .= 'Payment or charge not found';

                break;
            case 500:
                $message .= 'Unexpected error';

                break;
            case 0:
                $message .= 'Curl error: ' . $e->getMessage();

                break;
        }
        $this->logger->pushHandler(new StreamHandler($this->kernel->getLogDir() . '/' . self::LOG_FILE_NAME));
        $this->logger->critical($message);
        $this->logger->popHandler();

        return $message;
    }

    /**
     * Parse json error message and fetch error message readable for users
     *
     * @param string $msgJson
     */
    public function parseError($msgJson)
    {
        $msgArr   = json_decode($msgJson, true);
        $errorStr = '';

        if (isset($msgArr['errors'])) {
            foreach ($msgArr['errors'] as $v) {
                foreach ($v as $error) {
                    $errorStr .= $error;
                }
            }
        }

        return $errorStr;
    }
}
