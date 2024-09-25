<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook;

use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeInterface;
use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeTrait;
use NexiNets\CheckoutApi\Model\Webhook\Data\Amount;
use NexiNets\CheckoutApi\Model\Webhook\Data\CancelCreatedData;
use NexiNets\CheckoutApi\Model\Webhook\Data\OrderItem;

class CancelCreated implements WebhookInterface, JsonDeserializeInterface
{
    use JsonDeserializeTrait;

    public function __construct(
        private readonly string $id,
        private readonly \DateTimeInterface $timestamp,
        private readonly int $merchantId,
        private readonly EventNameEnum $event,
        private readonly CancelCreatedData $data,
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

    public function getMerchantId(): int
    {
        return $this->merchantId;
    }

    public function getEvent(): EventNameEnum
    {
        return $this->event;
    }

    public function getData(): CancelCreatedData
    {
        return $this->data;
    }

    public static function fromJson(string $string): CancelCreated
    {
        $payload = self::jsonDeserialize($string);

        return new self(
            id: $payload['id'],
            timestamp: new \DateTimeImmutable($payload['timestamp']),
            merchantId: $payload['merchantId'],
            event: EventNameEnum::from($payload['event']),
            data: self::createCancelCreated($payload['data'])
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createCancelCreated(array $data): CancelCreatedData
    {
        return new CancelCreatedData(
            paymentId: $data['paymentId'],
            cancelId: $data['cancelId'],
            amount: new Amount(...$data['amount']),
            orderItems: array_map(fn (array $orderItem) => new OrderItem(...$orderItem), $data['orderItems']),
        );
    }
}
