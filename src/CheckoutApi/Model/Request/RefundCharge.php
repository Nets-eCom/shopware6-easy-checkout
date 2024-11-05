<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request;

abstract class RefundCharge implements \JsonSerializable
{
    public function __construct(
        protected ?string $myReference = null,
    ) {
    }

    abstract public function getAmount(): int;

    /**
     * @return array{
     *     amount: int,
     *     myReference: ?string,
     * }
     */
    public function jsonSerialize(): array
    {
        $result = [
            'amount' => $this->getAmount(),
        ];

        if ($this->myReference !== null) {
            $result['myReference'] = $this->myReference;
        }

        return $result;
    }
}
