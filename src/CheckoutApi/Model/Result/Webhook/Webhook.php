<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\Webhook;

use NexiNets\CheckoutApi\Model\Result\AbstractResult;
use NexiNets\CheckoutApi\Model\Result\Webhook\Data\ChargeCreated;

class Webhook extends AbstractResult
{
    public function __construct(
        private readonly string $id,
        private readonly string $timestamp,
        private readonly string $merchantNumber,
        private readonly EventNameEnum $event,
        private readonly ?Data $data,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function getMerchantNumber(): string
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
        $payload['event'] = EventNameEnum::from($payload['event']);
        $payload['data'] = match ($payload['event']) {
            EventNameEnum::PAYMENT_CREATED => null /* @todo */,
            EventNameEnum::PAYMENT_RESERVATION_CREATED => null /* @todo */,
            EventNameEnum::PAYMENT_RESERVATION_CREATED_V2 => null /* @todo */,
            EventNameEnum::PAYMENT_RESERVATION_FAILED => null /* @todo */,
            EventNameEnum::PAYMENT_CHECKOUT_COMPLETED => null /* @todo */,
            EventNameEnum::PAYMENT_CHARGE_CREATED => new ChargeCreated(...$payload['data']),
            EventNameEnum::PAYMENT_CHARGE_FAILED => null /* @todo */,
            EventNameEnum::PAYMENT_REFUND_INITIATED => null /* @todo */,
            EventNameEnum::PAYMENT_REFUND_FAILED => null /* @todo */,
            EventNameEnum::PAYMENT_REFUND_COMPLETED => null /* @todo */,
            EventNameEnum::PAYMENT_CANCEL_CREATED => null /* @todo */,
            EventNameEnum::PAYMENT_CANCEL_FAILED => null /* @todo */,
        };

        return new self(...$payload);
    }
}
