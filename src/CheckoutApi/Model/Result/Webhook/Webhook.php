<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\Webhook;

use NexiNets\CheckoutApi\Model\Result\AbstractResult;

class Webhook extends AbstractResult
{
    public function __construct(
        private readonly string $id,
        private readonly string $timestamp,
        private readonly string $merchantNumber,
        private readonly string $event,
        private readonly Data $data,
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

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getData(): Data
    {
        return $this->data;
    }

    public static function fromJson(string $string): Webhook
    {
        return new self(...self::jsonDeserialize($string));
    }
}
