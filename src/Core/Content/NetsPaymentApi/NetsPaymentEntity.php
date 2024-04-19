<?php

declare(strict_types=1);

namespace Nets\Checkout\Core\Content\NetsPaymentApi;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class NetsPaymentEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $order_id;

    protected ?string $charge_id;

    protected string $operation_type;

    protected ?float $operation_amount;

    protected ?float $amount_available;

    public function getOrderId(): ?string
    {
        return $this->order_id;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->order_id = $orderId;
    }

    public function getChargeId(): ?string
    {
        return $this->charge_id;
    }

    public function setChargeId(?string $chargeId): void
    {
        $this->charge_id = $chargeId;
    }

    public function getOperationType(): ?string
    {
        return $this->operation_type;
    }

    public function setOperationType(?string $operationType): void
    {
        $this->operation_type = $operationType;
    }

    public function getOperationAmt(): ?float
    {
        return $this->operation_amount;
    }

    public function setOperationAmt(?float $operationAmt): void
    {
        $this->operation_amount = $operationAmt;
    }

    public function getAvailableAmt(): ?float
    {
        return $this->amount_available;
    }

    public function setAvailableAmt(?float $amtAvailable): void
    {
        $this->amount_available = $amtAvailable;
    }
}
