<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook;

use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeInterface;
use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeTrait;
use NexiNets\CheckoutApi\Model\Webhook\Data\Amount;
use NexiNets\CheckoutApi\Model\Webhook\Data\ChargeCreatedData;
use NexiNets\CheckoutApi\Model\Webhook\Data\OrderItem;

class ChargeCreated implements WebhookInterface, JsonDeserializeInterface
{
    use JsonDeserializeTrait;

    public function __construct(
        private readonly string $id,
        private readonly \DateTimeInterface $timestamp,
        private readonly int $merchantNumber,
        private readonly EventNameEnum $event,
        private readonly ChargeCreatedData $data,
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

    public function getData(): ChargeCreatedData
    {
        return $this->data;
    }

    public static function fromJson(string $string): ChargeCreated
    {
        $payload = self::jsonDeserialize($string);

        return new self(
            id: $payload['id'],
            timestamp: new \DateTimeImmutable($payload['timestamp']),
            merchantNumber: $payload['merchantNumber'],
            event: EventNameEnum::from($payload['event']),
            data: self::createChargeCreated($payload['data'])
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createChargeCreated(array $data): ChargeCreatedData
    {
        return new ChargeCreatedData(
            paymentId: $data['paymentId'],
            chargeId: $data['chargeId'],
            paymentMethod: $data['paymentMethod'],
            paymentType: $data['paymentType'],
            orderItems: array_map(fn (array $orderItem) => new OrderItem(...$orderItem), $data['orderItems']),
            amount: new Amount(...$data['amount']),
        );
    }
}
