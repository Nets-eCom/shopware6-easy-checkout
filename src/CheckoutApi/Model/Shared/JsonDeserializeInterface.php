<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Shared;

interface JsonDeserializeInterface
{
    public static function fromJson(string $string): JsonDeserializeInterface;
}
