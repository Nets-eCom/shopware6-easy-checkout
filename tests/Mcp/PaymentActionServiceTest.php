<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Mcp;

use Nexi\Checkout\Administration\Model\ChargeData;
use Nexi\Checkout\Administration\Model\RefundData;
use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Mcp\PaymentActionService;
use Nexi\Checkout\Order\OrderCancel;
use Nexi\Checkout\Order\OrderCharge;
use Nexi\Checkout\Order\OrderRefund;
use Nexi\Checkout\Tests\Fixture\RetrievePaymentResultFixture;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

final class PaymentActionServiceTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function testAmountIsConvertedToExactCents(string $amount, string $expected): void
    {
        $service = $this->createService(RetrievePaymentResultFixture::reserved()->getPayment());

        $result = $service->charge($this->createTransaction(), $amount, '');

        $this->assertSame($expected, $result['amount']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validAmounts(): iterable
    {
        yield 'whole amount' => ['1', '1.00'];
        yield 'single decimal' => ['0.5', '0.50'];
        yield 'exact cents' => ['0.29', '0.29'];
        yield 'leading zeros and whitespace' => [' 000.01 ', '0.01'];
        yield 'remaining balance' => ['', '1.00'];
    }

    #[DataProvider('invalidAmounts')]
    public function testInvalidAmountIsRejected(string $amount): void
    {
        $service = $this->createService(RetrievePaymentResultFixture::reserved()->getPayment());

        $this->expectException(\InvalidArgumentException::class);

        $service->charge($this->createTransaction(), $amount, '');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAmounts(): iterable
    {
        yield 'zero' => ['0.00'];
        yield 'negative' => ['-0.01'];
        yield 'too many decimals' => ['0.001'];
        yield 'scientific notation' => ['1e-2'];
        yield 'overflow' => ['999999999999999999999999999999'];
    }

    public function testChargeReturnsBoundConfirmationPreview(): void
    {
        $payment = RetrievePaymentResultFixture::reserved()->getPayment();
        $service = $this->createService($payment);

        $result = $service->charge($this->createTransaction(), '0.50', '');

        $this->assertFalse($result['executed']);
        $this->assertSame('0.50', $result['amount']);
        $this->assertSame(
            \sprintf('CONFIRM CHARGE payment-id 0.50 EUR AVAILABLE 1.00 STATUS %s', $payment->getStatus()->value),
            $result['requiredConfirmation']
        );
    }

    public function testPartialChargeDelegatesAfterExactConfirmation(): void
    {
        $payment = RetrievePaymentResultFixture::reserved()->getPayment();
        $orderCharge = $this->createMock(OrderCharge::class);
        $orderCharge->expects($this->once())
            ->method('partialCharge')
            ->with(
                $this->isInstanceOf(OrderEntity::class),
                $this->callback(static fn (ChargeData $data): bool => $data->getAmount() === 0.5)
            );
        $service = $this->createService($payment, orderCharge: $orderCharge);

        $result = $service->charge(
            $this->createTransaction(),
            '0.50',
            \sprintf('CONFIRM CHARGE payment-id 0.50 EUR AVAILABLE 1.00 STATUS %s', $payment->getStatus()->value)
        );

        $this->assertSame('service_call_completed', $result['status']);
        $this->assertSame('charge', $result['action']);
    }

    public function testFullChargeDelegatesAfterExactConfirmation(): void
    {
        $payment = RetrievePaymentResultFixture::reserved()->getPayment();
        $orderCharge = $this->createMock(OrderCharge::class);
        $orderCharge->expects($this->once())->method('fullCharge');
        $service = $this->createService($payment, orderCharge: $orderCharge);

        $result = $service->charge(
            $this->createTransaction(),
            '',
            \sprintf('CONFIRM CHARGE payment-id 1.00 EUR AVAILABLE 1.00 STATUS %s', $payment->getStatus()->value)
        );

        $this->assertSame('service_call_completed', $result['status']);
        $this->assertSame('1.00', $result['amount']);
    }

    public function testChargeRejectsAmountAboveAvailableBalance(): void
    {
        $service = $this->createService(RetrievePaymentResultFixture::reserved()->getPayment());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the available amount');

        $service->charge($this->createTransaction(), '1.01', '');
    }

    public function testRefundDelegatesAfterExactConfirmation(): void
    {
        $payment = RetrievePaymentResultFixture::fullyChargedWithMultipleCharges()->getPayment();
        $context = Context::createDefaultContext();
        $orderRefund = $this->createMock(OrderRefund::class);
        $orderRefund->expects($this->once())
            ->method('partialRefund')
            ->with(
                $this->isInstanceOf(OrderEntity::class),
                $this->callback(static fn (RefundData $data): bool => $data->getAmount() === 10.0
                    && $data->getContext() === $context)
            );
        $service = $this->createService($payment, orderRefund: $orderRefund);

        $result = $service->refund(
            $this->createTransaction(),
            $context,
            '',
            \sprintf('CONFIRM REFUND payment-id 10.00 EUR AVAILABLE 10.00 STATUS %s', $payment->getStatus()->value)
        );

        $this->assertSame('service_call_completed', $result['status']);
        $this->assertSame('refund', $result['action']);
    }

    public function testCancelReturnsConfirmationPreview(): void
    {
        $payment = RetrievePaymentResultFixture::reserved()->getPayment();
        $service = $this->createService($payment);

        $result = $service->cancel($this->createTransaction(), '');

        $this->assertFalse($result['executed']);
        $this->assertSame(
            \sprintf('CONFIRM CANCEL payment-id 1.00 EUR STATUS %s', $payment->getStatus()->value),
            $result['requiredConfirmation']
        );
    }

    public function testCancelDelegatesAfterExactConfirmation(): void
    {
        $payment = RetrievePaymentResultFixture::reserved()->getPayment();
        $orderCancel = $this->createMock(OrderCancel::class);
        $orderCancel->expects($this->once())->method('cancel');
        $service = $this->createService($payment, orderCancel: $orderCancel);

        $result = $service->cancel(
            $this->createTransaction(),
            \sprintf('CONFIRM CANCEL payment-id 1.00 EUR STATUS %s', $payment->getStatus()->value)
        );

        $this->assertSame('service_call_completed', $result['status']);
        $this->assertSame('cancel', $result['action']);
    }

    public function testManualChargeIsRejectedWhenAutoChargeIsEnabled(): void
    {
        $payment = RetrievePaymentResultFixture::reserved()->getPayment();
        $orderCharge = $this->createMock(OrderCharge::class);
        $orderCharge->expects($this->never())->method('fullCharge');
        $orderCharge->expects($this->never())->method('partialCharge');
        $service = $this->createService($payment, orderCharge: $orderCharge, isAutoCharge: true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Manual charge is disabled because auto-charge is enabled');

        $service->charge(
            $this->createTransaction(),
            '',
            \sprintf('CONFIRM CHARGE payment-id 1.00 EUR AVAILABLE 1.00 STATUS %s', $payment->getStatus()->value)
        );
    }

    private function createService(
        Payment $payment,
        ?OrderCharge $orderCharge = null,
        ?OrderRefund $orderRefund = null,
        ?OrderCancel $orderCancel = null,
        bool $isAutoCharge = false,
    ): PaymentActionService {
        $fetcher = $this->createStub(PaymentFetcherInterface::class);
        $fetcher->method('fetchPayment')->willReturn($payment);
        $configuration = $this->createConfiguredMock(ConfigurationProvider::class, [
            'isAutoCharge' => $isAutoCharge,
        ]);

        return new PaymentActionService(
            $fetcher,
            $configuration,
            $orderCharge ?? $this->createStub(OrderCharge::class),
            $orderRefund ?? $this->createStub(OrderRefund::class),
            $orderCancel ?? $this->createStub(OrderCancel::class),
        );
    }

    private function createTransaction(): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-id');
        $transaction->setCustomFields([
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID => 'payment-id',
        ]);

        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setOrderNumber('10001');
        $order->setSalesChannelId('sales-channel-id');
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        $transaction->setOrder($order);

        return $transaction;
    }
}
