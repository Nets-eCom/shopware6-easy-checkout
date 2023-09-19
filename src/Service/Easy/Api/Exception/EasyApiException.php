<?php

namespace Nets\Checkout\Service\Easy\Api\Exception;

/**
 * Description of EasyException
 *
 * @author mabe
 */
class EasyApiException extends \Exception
{
    public function __construct($message = '', $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getResponseErrors(): array
    {
        $errors = [];

        $jsonArr = json_decode($this->getMessage(), true);

        if (empty($jsonArr)) {
            $jsonArr['errors'] = ['unauthorized.access.error' => ['key' => 'Unauthorized access. Please check test/live secret keys']];
        }

        if ($jsonArr) {
            foreach ($jsonArr['errors'] as $id => $description) {
                foreach ($description as $desc) {
                    $errors[] = $desc;
                }
            }
        }

        return $errors;
    }
}
