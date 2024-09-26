<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook;

use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeInterface;
use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeTrait;
use NexiNets\CheckoutApi\Model\Webhook\Data\Amount;
use NexiNets\CheckoutApi\Model\Webhook\Data\CheckoutCompletedData;
use NexiNets\CheckoutApi\Model\Webhook\Data\Consumer;
use NexiNets\CheckoutApi\Model\Webhook\Data\Consumer\Address;
use NexiNets\CheckoutApi\Model\Webhook\Data\Consumer\PhoneNumber;
use NexiNets\CheckoutApi\Model\Webhook\Data\Order;
use NexiNets\CheckoutApi\Model\Webhook\Data\OrderItem;
use NexiNets\CheckoutApi\Model\Webhook\Shared\Data;

readonly class CheckoutCompleted implements WebhookInterface, JsonDeserializeInterface
{
    use JsonDeserializeTrait;

    public function __construct(
        private string $id,
        private int $merchantId,
        private \DateTimeInterface $timestamp,
        private EventNameEnum $eventName,
        private Data $data
    ) {
    }

    public static function fromJson(string $string): CheckoutCompleted
    {
        $payload = self::jsonDeserialize($string);

        return new self(
            $payload['id'],
            $payload['merchantId'],
            new \DateTimeImmutable($payload['timestamp']),
            EventNameEnum::PAYMENT_CHECKOUT_COMPLETED,
            self::createCheckoutCompleteData($payload['data'])
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMerchantId(): int
    {
        return $this->merchantId;
    }

    public function getTimestamp(): \DateTimeInterface
    {
        return $this->timestamp;
    }

    public function getEvent(): EventNameEnum
    {
        return $this->eventName;
    }

    public function getData(): Data
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createCheckoutCompleteData(array $data): CheckoutCompletedData
    {
        $order = $data['order'];
        $consumer = $data['consumer'];
        $phoneNumber = $consumer['phoneNumber'];

        return new CheckoutCompletedData(
            $data['paymentId'],
            new Order(
                new Amount(...$order['amount']),
                $order['reference'],
                array_map(fn (array $orderItem): OrderItem => new OrderItem(...$orderItem), $order['orderItems'])
            ),
            new Consumer(
                $consumer['firstName'],
                $consumer['lastName'],
                self::createAddress($consumer['billingAddress']),
                $consumer['country'],
                $consumer['email'],
                $consumer['ip'],
                new PhoneNumber($phoneNumber['prefix'], $phoneNumber['number']),
                self::createAddress($consumer['shippingAddress']),
            )
        );
    }

    /**
     * @param array<string, string> $address
     */
    private static function createAddress(array $address): Address
    {
        return new Address(
            $address['addressLine1'],
            $address['addressLine2'],
            $address['city'],
            $address['country'],
            $address['postcode'],
            $address['receiverLine'],
        );
    }
}
