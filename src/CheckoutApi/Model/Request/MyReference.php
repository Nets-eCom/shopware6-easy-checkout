<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request;

final readonly class MyReference implements \JsonSerializable
{
    public function __construct(
        private string $myReference
    ) {
    }

    /**
     * @return array{
     *     myReference: string
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'myReference' => $this->myReference,
        ];
    }
}
