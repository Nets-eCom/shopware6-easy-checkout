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
use NexiCheckout\Model\Result\RetrievePayment\OrderDetails;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiCheckout\Model\Result\RetrievePayment\Summary;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

final class PaymentActionServiceTest extends TestCase
{
    public function testAmountIsConvertedToExactCents(): void
    {
        $service = $this->createService();
        foreach ([['1', '1.00'], ['0.5', '0.50'], ['0.29', '0.29'], [' 000.01 ', '0.01'], ['', '1.00']] as [$amount, $expected]) {
            $result = $service->charge($this->createOrder(), $amount, '');
            $this->assertSame($expected, $result['amount']);
        }
    }

    public function testInvalidAmountsAreRejected(): void
    {
        $service = $this->createService();
        foreach (['0.00', '-0.01', '0.001', '1e-2', '999999999999999999999999999999'] as $amount) {
            try {
                $service->charge($this->createOrder(), $amount, '');
                $this->fail('Expected invalid amount to be rejected: ' . $amount);
            } catch (\InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function testChargeReturnsBoundConfirmationPreview(): void
    {
        $charge = $this->createMock(OrderCharge::class);
        $charge->expects($this->never())->method('partialCharge');
        $charge->expects($this->never())->method('fullCharge');
        $result = $this->createService(orderCharge: $charge)->charge($this->createOrder(), '0.50', '');
        $this->assertFalse($result['executed']);
        $this->assertSame('0.50', $result['amount']);
        $this->assertSame('CONFIRM CHARGE payment-id 0.50 EUR AVAILABLE 1.00 STATUS reserved', $result['requiredConfirmation']);
    }

    public function testPartialChargeDelegatesTheResolvedOrder(): void
    {
        $order = $this->createOrder();
        $charge = $this->createMock(OrderCharge::class);
        $charge->expects($this->once())->method('partialCharge')->with(
            $this->identicalTo($order),
            $this->callback(fn (ChargeData $data): bool => $data->getAmount() === 0.5)
        );
        $result = $this->createService(orderCharge: $charge)->charge(
            $order,
            '0.50',
            'CONFIRM CHARGE payment-id 0.50 EUR AVAILABLE 1.00 STATUS reserved'
        );
        $this->assertSame('service_call_completed', $result['status']);
        $this->assertSame('charge', $result['action']);
    }

    public function testFullChargeDelegatesTheResolvedOrder(): void
    {
        $order = $this->createOrder();
        $charge = $this->createMock(OrderCharge::class);
        $charge->expects($this->once())->method('fullCharge')->with($this->identicalTo($order));
        $result = $this->createService(orderCharge: $charge)->charge(
            $order,
            '',
            'CONFIRM CHARGE payment-id 1.00 EUR AVAILABLE 1.00 STATUS reserved'
        );
        $this->assertSame('service_call_completed', $result['status']);
        $this->assertSame('1.00', $result['amount']);
    }

    public function testChargeRejectsAmountAboveAvailableBalance(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the available amount');
        $this->createService()->charge($this->createOrder(), '1.01', '');
    }

    public function testRefundDelegatesTheResolvedOrder(): void
    {
        $order = $this->createOrder();
        $context = $this->createStub(Context::class);
        $refund = $this->createMock(OrderRefund::class);
        $refund->expects($this->once())->method('partialRefund')->with(
            $this->identicalTo($order),
            $this->callback(fn (RefundData $data): bool => $data->getAmount() === 10.0 && $data->getContext() === $context)
        );
        $result = $this->createService(orderRefund: $refund, charged: true)->refund(
            $order,
            $context,
            '',
            'CONFIRM REFUND payment-id 10.00 EUR AVAILABLE 10.00 STATUS charged'
        );
        $this->assertSame('service_call_completed', $result['status']);
        $this->assertSame('refund', $result['action']);
    }

    public function testCancelReturnsConfirmationPreview(): void
    {
        $cancel = $this->createMock(OrderCancel::class);
        $cancel->expects($this->never())->method('cancel');
        $result = $this->createService(orderCancel: $cancel)->cancel($this->createOrder(), '');
        $this->assertFalse($result['executed']);
        $this->assertSame('CONFIRM CANCEL payment-id 1.00 EUR STATUS reserved', $result['requiredConfirmation']);
    }

    public function testCancelDelegatesTheResolvedOrder(): void
    {
        $order = $this->createOrder();
        $cancel = $this->createMock(OrderCancel::class);
        $cancel->expects($this->once())->method('cancel')->with($this->identicalTo($order));
        $result = $this->createService(orderCancel: $cancel)->cancel(
            $order,
            'CONFIRM CANCEL payment-id 1.00 EUR STATUS reserved'
        );
        $this->assertSame('service_call_completed', $result['status']);
        $this->assertSame('cancel', $result['action']);
    }

    public function testManualChargeIsRejectedWhenAutoChargeIsEnabled(): void
    {
        $charge = $this->createMock(OrderCharge::class);
        $charge->expects($this->never())->method('fullCharge');
        $charge->expects($this->never())->method('partialCharge');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Manual charge is disabled because auto-charge is enabled');
        $this->createService(orderCharge: $charge, isAutoCharge: true)->charge(
            $this->createOrder(),
            '',
            'CONFIRM CHARGE payment-id 1.00 EUR AVAILABLE 1.00 STATUS reserved'
        );
    }

    public function testActionsRejectMissingEmptyAndMultipleTransactions(): void
    {
        foreach (['charge', 'refund', 'cancel'] as $action) {
            foreach ([null, 0, 2] as $count) {
                $order = $this->createOrder();
                if ($count === null) {
                    $order = new OrderEntity();
                } elseif ($count === 0) {
                    $order->setTransactions(new OrderTransactionCollection());
                } else {
                    $other = new OrderTransactionEntity();
                    $other->setId('other-transaction');
                    $order->getTransactions()->add($other);
                }

                $service = $this->createService();
                try {
                    match ($action) {
                        'charge' => $service->charge($order, '', ''),
                        'refund' => $service->refund($order, $this->createStub(Context::class), '', ''),
                        'cancel' => $service->cancel($order, ''),
                    };
                    $this->fail('Expected invalid transaction scope to be rejected');
                } catch (\InvalidArgumentException $exception) {
                    $this->assertStringContainsString('exactly one', $exception->getMessage());
                }
            }
        }
    }

    private function createService(
        ?OrderCharge $orderCharge = null,
        ?OrderRefund $orderRefund = null,
        ?OrderCancel $orderCancel = null,
        bool $isAutoCharge = false,
        bool $charged = false,
    ): PaymentActionService {
        $payment = $this->createStub(Payment::class);
        $payment->method('getStatus')->willReturn($charged ? PaymentStatusEnum::CHARGED : PaymentStatusEnum::RESERVED);
        $payment->method('getOrderDetails')->willReturn(new OrderDetails($charged ? 1000 : 100, 'EUR'));
        $summary = $this->createStub(Summary::class);
        $summary->method('getChargedAmount')->willReturn($charged ? 1000 : 0);
        $summary->method('getRefundedAmount')->willReturn(0);
        $payment->method('getSummary')->willReturn($summary);
        $fetcher = $this->createStub(PaymentFetcherInterface::class);
        $fetcher->method('fetchPayment')->willReturn($payment);
        $configuration = $this->createStub(ConfigurationProvider::class);
        $configuration->method('isAutoCharge')->willReturn($isAutoCharge);

        return new PaymentActionService(
            $fetcher,
            $configuration,
            $orderCharge ?? $this->createStub(OrderCharge::class),
            $orderRefund ?? $this->createStub(OrderRefund::class),
            $orderCancel ?? $this->createStub(OrderCancel::class),
        );
    }

    private function createOrder(): OrderEntity
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

        $nestedOrder = new OrderEntity();
        $nestedOrder->setId('order-id');
        $nestedOrder->setOrderNumber('10001');

        $transaction->setOrder($nestedOrder);

        return $order;
    }
}
