<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook;

use NexiNets\CheckoutApi\Model\Result\AbstractResult;
use NexiNets\CheckoutApi\Model\Webhook\Data\Amount;
use NexiNets\CheckoutApi\Model\Webhook\Data\InvoiceDetails;
use NexiNets\CheckoutApi\Model\Webhook\Data\RefundCompletedData;

class RefundCompleted extends AbstractResult implements WebhookInterface
{
    public function __construct(
        private readonly string $id,
        private readonly \DateTimeInterface $timestamp,
        private readonly int $merchantId,
        private readonly EventNameEnum $event,
        private readonly RefundCompletedData $data,
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

    public function getData(): RefundCompletedData
    {
        return $this->data;
    }

    public static function fromJson(string $string): RefundCompleted
    {
        $payload = self::jsonDeserialize($string);

        return new self(
            id: $payload['id'],
            timestamp: new \DateTimeImmutable($payload['timestamp']),
            merchantId: $payload['merchantId'],
            event: EventNameEnum::from($payload['event']),
            data: self::createRefundCompleted($payload['data'])
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createRefundCompleted(array $data): RefundCompletedData
    {
        return new RefundCompletedData(
            paymentId: $data['paymentId'],
            refundId: $data['refundId'],
            amount: new Amount(...$data['amount']),
            invoiceDetails: isset($data['invoiceDetails']) ? new InvoiceDetails(...$data['invoiceDetails']) : null,
        );
    }
}
