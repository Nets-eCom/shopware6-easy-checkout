<?php declare(strict_types=1);
namespace Nets\Checkout\Core\Content\NetsPaymentApi;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class NetsPaymentEntity extends Entity
{
    use EntityIdTrait;

    /**
     *
     * @var string|null
     */
    protected $order_id;

    /**
     *
     * @var string|null
     */
    protected $charge_id;

    /**
     *
     * @var string
     */
    protected $operation_type;

    /**
     *
     * @var string
     */
    protected $operation_amount;

    /**
     *
     * @var string
     */
    protected $amount_available;

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

    public function getOperationAmt(): ?string
    {
        return $this->operation_amount;
    }

    public function setOperationAmt(?string $operationAmt): void
    {
        $this->operation_amount = $operationAmt;
    }

    public function getAvailableAmt(): ?string
    {
        return $this->amount_available;
    }

    public function setAvailableAmt(?string $amtAvailable): void
    {
        $this->amount_available = $amtAvailable;
    }
}