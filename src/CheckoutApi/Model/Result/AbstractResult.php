<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result;

abstract class AbstractResult
{
    abstract public static function fromJson(string $string): AbstractResult;

    /**
     * @return array<mixed>
     */
    protected static function jsonDeserialize(string $string): array
    {
        return json_decode($string, true, 512, \JSON_INVALID_UTF8_IGNORE);
    }
}
