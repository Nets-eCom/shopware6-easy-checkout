<?php

declare(strict_types=1);

namespace Nexi\Checkout\Mcp;

use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use NexiCheckout\Model\Result\RetrievePayment\Item;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

final class PaymentDetailsService
{
    public function __construct(private readonly PaymentFetcherInterface $paymentFetcher)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(OrderTransactionEntity $transaction): array
    {
        $order = $transaction->getOrder();
        if (!$order instanceof OrderEntity) {
            throw new \LogicException('Order association is not loaded.');
        }

        $paymentId = (string) $transaction->getCustomFieldsValue(
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID
        );
        $payment = $this->paymentFetcher->fetchPayment(
            $order->getSalesChannelId(),
            $paymentId
        );
        $summary = $payment->getSummary();
        $orderAmount = $payment->getOrderDetails()->getAmount();
        $chargedAmount = $summary->getChargedAmount();
        $refundedAmount = $summary->getRefundedAmount();
        $isCancelled = $payment->getStatus() === PaymentStatusEnum::CANCELLED;

        return [
            'orderId' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'transactionId' => $transaction->getId(),
            'paymentId' => $paymentId,
            'salesChannelId' => $order->getSalesChannelId(),
            'currency' => $order->getCurrency()?->getIsoCode(),
            'paymentVia' => $payment->getPaymentDetails()?->getPaymentMethod(),
            'status' => $payment->getStatus()->value,
            'createdAt' => $payment->getCreated()->format(\DATE_ATOM),
            'orderAmount' => $this->formatAmount($orderAmount),
            'chargedAmount' => $this->formatAmount($chargedAmount),
            'remainingChargeAmount' => $this->formatAmount($isCancelled ? 0 : max(0, $orderAmount - $chargedAmount)),
            'refundedAmount' => $this->formatAmount($refundedAmount),
            'remainingRefundAmount' => $this->formatAmount($isCancelled ? 0 : max(0, $chargedAmount - $refundedAmount)),
            'orderItems' => $this->buildOrderItems($payment, $transaction),
            'charges' => $this->buildCharges($payment),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildCharges(Payment $payment): array
    {
        $result = [];
        foreach ($payment->getCharges() ?? [] as $charge) {
            $result[] = [
                'chargeId' => $charge->getChargeId(),
                'amount' => $this->formatAmount($charge->getAmount()),
                'items' => array_map(fn (Item $item): array => [
                    'reference' => $item->getReference(),
                    'name' => $item->getName(),
                    'quantity' => $item->getQuantity(),
                    'unit' => $item->getUnit(),
                    'unitPrice' => $this->formatAmount($item->getUnitPrice()),
                    'grossTotalAmount' => $this->formatAmount($item->getGrossTotalAmount()),
                ], $charge->getOrderItems()),
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildOrderItems(Payment $payment, OrderTransactionEntity $transaction): array
    {
        $storedOrder = $transaction->getCustomFieldsValue(
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_ORDER
        );
        if (!\is_array($storedOrder) || !\is_array($storedOrder['items'] ?? null)) {
            return [];
        }

        return array_map(function (array $item) use ($payment): array {
            return [
                'reference' => (string) ($item['reference'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'quantity' => $item['quantity'] ?? 0,
                'unitPrice' => $this->formatAmount((int) ($item['unitPrice'] ?? 0)),
                'grossTotalAmount' => $this->formatAmount((int) ($item['grossTotalAmount'] ?? 0)),
                'quantityCharged' => $this->chargedQuantityForItem($item, $payment),
            ];
        }, $storedOrder['items']);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function chargedQuantityForItem(array $item, Payment $payment): float
    {
        $quantity = 0.0;

        foreach ($payment->getCharges() ?? [] as $charge) {
            foreach ($charge->getOrderItems() as $chargedItem) {
                if ($chargedItem->getReference() !== ($item['reference'] ?? null)
                    || $chargedItem->getName() !== ($item['name'] ?? null)
                ) {
                    continue;
                }

                $quantity += $chargedItem->getQuantity();
            }
        }

        return $quantity;
    }

    private function formatAmount(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
