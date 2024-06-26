<?php

declare(strict_types=1);

namespace NexiNets\Core\Content\NexiNetsPaymentApi;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class NexiNetsPaymentEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $order_id;

    protected ?string $charge_id;

    protected ?string $data;

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

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(?string $data): void
    {
        $this->charge_id = $data;
    }

}