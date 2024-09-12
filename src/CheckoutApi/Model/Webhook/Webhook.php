<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook;

use NexiNets\CheckoutApi\Model\Result\AbstractResult;

class Webhook extends AbstractResult
{
    public static function fromJson(string $string): AbstractResult&WebhookInterface
    {
        $payload = self::jsonDeserialize($string);
        $event = EventNameEnum::from($payload['event']);

        return match ($event) {
            EventNameEnum::PAYMENT_CREATED => null /* @todo */,
            EventNameEnum::PAYMENT_RESERVATION_CREATED => null /* @todo */,
            EventNameEnum::PAYMENT_RESERVATION_CREATED_V2 => null /* @todo */,
            EventNameEnum::PAYMENT_RESERVATION_FAILED => null /* @todo */,
            EventNameEnum::PAYMENT_CHECKOUT_COMPLETED => null /* @todo */,
            EventNameEnum::PAYMENT_CHARGE_CREATED => ChargeCreated::fromJson($string),
            EventNameEnum::PAYMENT_CHARGE_FAILED => null /* @todo */,
            EventNameEnum::PAYMENT_REFUND_INITIATED => null /* @todo */,
            EventNameEnum::PAYMENT_REFUND_FAILED => null /* @todo */,
            EventNameEnum::PAYMENT_REFUND_COMPLETED => RefundCompleted::fromJson($string),
            EventNameEnum::PAYMENT_CANCEL_CREATED => null /* @todo */,
            EventNameEnum::PAYMENT_CANCEL_FAILED => null /* @todo */,
        };
    }
}
