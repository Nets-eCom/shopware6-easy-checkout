<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook;

use NexiNets\CheckoutApi\Model\Result\AbstractResult;
use NexiNets\CheckoutApi\Model\Webhook\Data\Amount;
use NexiNets\CheckoutApi\Model\Webhook\Data\ChargeCreated;
use NexiNets\CheckoutApi\Model\Webhook\Data\OrderItem;

class Webhook extends AbstractResult
{
    public function __construct(
        private readonly string $id,
        private readonly \DateTimeInterface $timestamp,
        private readonly int $merchantNumber,
        private readonly EventNameEnum $event,
        private readonly ?Data $data,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTimestamp(): \DateTimeInterface
    {
        return $this->timestamp;
    }

    public function getMerchantNumber(): int
    {
        return $this->merchantNumber;
    }

    public function getEvent(): EventNameEnum
    {
        return $this->event;
    }

    public function getData(): ?Data
    {
        return $this->data;
    }

    public static function fromJson(string $string): Webhook
    {
        $payload = self::jsonDeserialize($string);
        $event = EventNameEnum::from($payload['event']);
        $data = match ($event) {
            EventNameEnum::PAYMENT_CREATED => null /* @todo */,
            EventNameEnum::PAYMENT_RESERVATION_CREATED => null /* @todo */,
            EventNameEnum::PAYMENT_RESERVATION_CREATED_V2 => null /* @todo */,
            EventNameEnum::PAYMENT_RESERVATION_FAILED => null /* @todo */,
            EventNameEnum::PAYMENT_CHECKOUT_COMPLETED => null /* @todo */,
            EventNameEnum::PAYMENT_CHARGE_CREATED => self::createChargeCreated($payload['data']),
            EventNameEnum::PAYMENT_CHARGE_FAILED => null /* @todo */,
            EventNameEnum::PAYMENT_REFUND_INITIATED => null /* @todo */,
            EventNameEnum::PAYMENT_REFUND_FAILED => null /* @todo */,
            EventNameEnum::PAYMENT_REFUND_COMPLETED => null /* @todo */,
            EventNameEnum::PAYMENT_CANCEL_CREATED => null /* @todo */,
            EventNameEnum::PAYMENT_CANCEL_FAILED => null /* @todo */,
        };

        return new self(
            id: $payload['id'],
            timestamp: new \DateTimeImmutable($payload['timestamp']),
            merchantNumber: $payload['merchantNumber'],
            event: $event,
            data: $data
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createChargeCreated(array $data): ChargeCreated
    {
        return new ChargeCreated(
            paymentId: $data['paymentId'],
            chargeId: $data['chargeId'],
            paymentMethod: $data['paymentMethod'],
            paymentType: $data['paymentType'],
            orderItems: array_map(fn (array $orderItem) => new OrderItem(...$orderItem), $data['orderItems']),
            amount: new Amount(...$data['amount']),
        );
    }
}
