<?php

namespace Nets\Checkout\Service\Easy\Api\Exception;

use Throwable;

/**
 * Description of EasyException
 *
 * @author mabe
 */
class EasyApiException extends \Exception {

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getResponseBoby()
    {
        $str =  $this->getMessage();
        $p = 'response:';
        $pos =  strpos($str, $p) + strlen($p);
        return substr($str, $pos);
    }

    public function getResponseErrors()
    {
        $str =  $this->getMessage();

        $jsonArr = json_decode( $str, true );

        $errors = [];

        foreach($jsonArr['errors'] as $id => $description) {
            foreach ($description as $desc) {
                $errors[] = $desc;
            }
        }

        return $errors;
    }
}
