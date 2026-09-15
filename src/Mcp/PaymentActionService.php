<?php

declare(strict_types=1);

namespace Nexi\Checkout\Mcp;

use Nexi\Checkout\Administration\Model\ChargeData;
use Nexi\Checkout\Administration\Model\RefundData;
use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Order\Exception\OrderCancelException;
use Nexi\Checkout\Order\Exception\OrderChargeException;
use Nexi\Checkout\Order\Exception\OrderRefundException;
use Nexi\Checkout\Order\OrderCancel;
use Nexi\Checkout\Order\OrderCharge;
use Nexi\Checkout\Order\OrderRefund;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

final class PaymentActionService
{
    public function __construct(
        private readonly PaymentFetcherInterface $paymentFetcher,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly OrderCharge $orderCharge,
        private readonly OrderRefund $orderRefund,
        private readonly OrderCancel $orderCancel,
    ) {
    }

    /**
     * @throws OrderChargeException
     *
     * @return array<string, mixed>
     */
    public function charge(OrderTransactionEntity $transaction, string $amount, string $confirmation): array
    {
        $order = $this->order($transaction);
        if ($this->configurationProvider->isAutoCharge($order->getSalesChannelId())) {
            throw new \InvalidArgumentException('Manual charge is disabled because auto-charge is enabled for this sales channel.');
        }

        $payment = $this->fetchPayment($transaction);
        $status = $payment->getStatus();
        $this->assertPaymentStatus($status, [
            PaymentStatusEnum::RESERVED,
            PaymentStatusEnum::PARTIALLY_CHARGED,
            PaymentStatusEnum::PARTIALLY_REFUNDED,
        ], 'charged');

        $orderDetails = $payment->getOrderDetails();
        $remaining = max(0, $orderDetails->getAmount() - $payment->getSummary()->getChargedAmount());
        $amountInCents = $this->resolveAmount($amount, $remaining, 'charge');
        $currency = $orderDetails->getCurrency();
        $expectedConfirmation = $this->confirmation(
            'CHARGE',
            $this->paymentId($transaction),
            $amountInCents,
            $remaining,
            $currency,
            $status
        );

        if (!hash_equals($expectedConfirmation, $confirmation)) {
            return $this->preview($transaction, 'charge', $amountInCents, $currency, $status, $expectedConfirmation);
        }

        if ($status === PaymentStatusEnum::RESERVED && $amountInCents === $orderDetails->getAmount()) {
            $this->orderCharge->fullCharge($order);

            return $this->executionResponse($transaction, 'charge', $amountInCents, $currency);
        }

        $this->orderCharge->partialCharge($order, new ChargeData($amountInCents / 100));

        return $this->executionResponse($transaction, 'charge', $amountInCents, $currency);
    }

    /**
     * @throws OrderRefundException
     *
     * @return array<string, mixed>
     */
    public function refund(
        OrderTransactionEntity $transaction,
        Context $context,
        string $amount,
        string $confirmation,
    ): array {
        $payment = $this->fetchPayment($transaction);
        $status = $payment->getStatus();
        $this->assertPaymentStatus($status, [
            PaymentStatusEnum::CHARGED,
            PaymentStatusEnum::PARTIALLY_CHARGED,
            PaymentStatusEnum::PARTIALLY_REFUNDED,
        ], 'refunded');

        $remaining = max(0, $payment->getSummary()->getChargedAmount() - $payment->getSummary()->getRefundedAmount());
        $amountInCents = $this->resolveAmount($amount, $remaining, 'refund');
        $currency = $payment->getOrderDetails()->getCurrency();
        $expectedConfirmation = $this->confirmation(
            'REFUND',
            $this->paymentId($transaction),
            $amountInCents,
            $remaining,
            $currency,
            $status
        );

        if (!hash_equals($expectedConfirmation, $confirmation)) {
            return $this->preview($transaction, 'refund', $amountInCents, $currency, $status, $expectedConfirmation);
        }

        $refundData = new RefundData($amountInCents / 100);
        $refundData->setContext($context);

        $this->orderRefund->partialRefund($this->order($transaction), $refundData);

        return $this->executionResponse($transaction, 'refund', $amountInCents, $currency);
    }

    /**
     * @throws OrderCancelException
     *
     * @return array<string, mixed>
     */
    public function cancel(OrderTransactionEntity $transaction, string $confirmation): array
    {
        $payment = $this->fetchPayment($transaction);
        $status = $payment->getStatus();
        $this->assertPaymentStatus($status, [PaymentStatusEnum::RESERVED], 'cancelled');

        $amountInCents = $payment->getOrderDetails()->getAmount();
        $currency = $payment->getOrderDetails()->getCurrency();
        $expectedConfirmation = \sprintf(
            'CONFIRM CANCEL %s %s %s STATUS %s',
            $this->paymentId($transaction),
            $this->formatAmount($amountInCents),
            $currency,
            $status->value
        );

        if (!hash_equals($expectedConfirmation, $confirmation)) {
            return $this->preview($transaction, 'cancel', $amountInCents, $currency, $status, $expectedConfirmation);
        }

        $this->orderCancel->cancel($this->order($transaction));

        return $this->executionResponse($transaction, 'cancel', $amountInCents, $currency);
    }

    private function fetchPayment(OrderTransactionEntity $transaction): Payment
    {
        return $this->paymentFetcher->fetchPayment(
            $this->order($transaction)->getSalesChannelId(),
            $this->paymentId($transaction)
        );
    }

    /**
     * @param list<PaymentStatusEnum> $allowedStatuses
     */
    private function assertPaymentStatus(
        PaymentStatusEnum $status,
        array $allowedStatuses,
        string $action,
    ): void {
        if (\in_array($status, $allowedStatuses, true)) {
            return;
        }

        throw new \InvalidArgumentException(\sprintf(
            'Payment cannot be %s from status "%s".',
            $action,
            $status->value
        ));
    }

    private function resolveAmount(string $amount, int $availableAmount, string $action): int
    {
        if ($availableAmount <= 0) {
            throw new \InvalidArgumentException(\sprintf('No amount remains available to %s.', $action));
        }

        $amountInCents = trim($amount) === '' ? $availableAmount : $this->parseAmount($amount);
        if ($amountInCents > $availableAmount) {
            throw new \InvalidArgumentException(\sprintf(
                'Requested %s amount %s exceeds the available amount %s.',
                $action,
                $this->formatAmount($amountInCents),
                $this->formatAmount($availableAmount)
            ));
        }

        return $amountInCents;
    }

    private function parseAmount(string $amount): int
    {
        $amount = trim($amount);
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $amount) !== 1) {
            throw new \InvalidArgumentException('Amount must be a positive decimal string with at most two decimal places.');
        }

        $parsedAmount = round((float) $amount * 100);
        if (!is_finite($parsedAmount) || $parsedAmount >= \PHP_INT_MAX) {
            throw new \InvalidArgumentException('Amount is too large.');
        }

        if ($parsedAmount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        return (int) $parsedAmount;
    }

    private function confirmation(
        string $action,
        string $paymentId,
        int $amount,
        int $availableAmount,
        string $currency,
        PaymentStatusEnum $status,
    ): string {
        return \sprintf(
            'CONFIRM %s %s %s %s AVAILABLE %s STATUS %s',
            $action,
            $paymentId,
            $this->formatAmount($amount),
            $currency,
            $this->formatAmount($availableAmount),
            $status->value
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function preview(
        OrderTransactionEntity $transaction,
        string $action,
        int $amount,
        string $currency,
        PaymentStatusEnum $status,
        string $confirmation,
    ): array {
        return [
            'action' => $action,
            'executed' => false,
            'orderId' => $this->order($transaction)->getId(),
            'orderNumber' => $this->order($transaction)->getOrderNumber(),
            'transactionId' => $transaction->getId(),
            'paymentId' => $this->paymentId($transaction),
            'amount' => $this->formatAmount($amount),
            'currency' => $currency,
            'paymentStatus' => $status->value,
            'requiredConfirmation' => $confirmation,
            'note' => 'Call this tool again with requiredConfirmation exactly as returned. Do not retry an executed mutation automatically.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function executionResponse(
        OrderTransactionEntity $transaction,
        string $action,
        int $amount,
        string $currency,
    ): array {
        return [
            'action' => $action,
            'orderId' => $this->order($transaction)->getId(),
            'orderNumber' => $this->order($transaction)->getOrderNumber(),
            'transactionId' => $transaction->getId(),
            'paymentId' => $this->paymentId($transaction),
            'amount' => $this->formatAmount($amount),
            'currency' => $currency,
            'status' => 'service_call_completed',
            'webhookPending' => true,
            'note' => 'Fetch fresh payment details to verify the resulting payment state.',
        ];
    }

    private function formatAmount(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }

    private function paymentId(OrderTransactionEntity $transaction): string
    {
        return (string) $transaction->getCustomFieldsValue(
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID
        );
    }

    private function order(OrderTransactionEntity $transaction): OrderEntity
    {
        $order = $transaction->getOrder();
        if (!$order instanceof OrderEntity) {
            throw new \LogicException('Order association is not loaded.');
        }

        return $order;
    }
}
