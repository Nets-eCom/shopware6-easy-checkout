<?php

declare(strict_types=1);

namespace Nexi\Checkout\Entity\NexiPaymentApi;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class NexiPaymentEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $orderId = null;

    protected ?string $chargeId = null;

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getChargeId(): ?string
    {
        return $this->chargeId;
    }

    public function setChargeId(?string $chargeId): void
    {
        $this->chargeId = $chargeId;
    }
}
