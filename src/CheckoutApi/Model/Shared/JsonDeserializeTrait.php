<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Shared;

trait JsonDeserializeTrait
{
    /**
     * @return array<mixed>
     */
    protected static function jsonDeserialize(string $string): array
    {
        return json_decode($string, true, 512, \JSON_INVALID_UTF8_IGNORE);
    }
}
