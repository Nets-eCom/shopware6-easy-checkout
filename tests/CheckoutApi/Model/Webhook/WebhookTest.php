<?php declare(strict_types=1);

namespace NexiNets\Tests\CheckoutApi\Model\Webhook;

use NexiNets\CheckoutApi\Model\Webhook\ChargeCreated;
use NexiNets\CheckoutApi\Model\Webhook\RefundCompleted;
use NexiNets\CheckoutApi\Model\Webhook\Webhook;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    #[DataProvider('providePayload')]
    public function testCreateFromJson(string $payload, string $eventName, string $dataClass, string $paymentId): void
    {
        $result = Webhook::fromJson($payload);

        $this->assertSame($eventName, $result->getEvent()->value);
        $this->assertInstanceOf($dataClass, $result);
        $this->assertSame($paymentId, $result->getData()->getPaymentId());
    }

    /**
     * @return iterable<array{string, string, string, string}>
     */
    public static function providePayload(): iterable
    {
        yield [
            file_get_contents(__DIR__ . '/payloads/payment.charge.created.v2.json'),
            'payment.charge.created.v2',
            ChargeCreated::class,
            '025400006091b1ef6937598058c4e487',
        ];

        yield [
            file_get_contents(__DIR__ . '/payloads/payment.refund.completed.json'),
            'payment.refund.completed',
            RefundCompleted::class,
            '025400006091b1ef6937598058c4e487',
        ];
    }
}
