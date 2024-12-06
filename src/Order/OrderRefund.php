<?php

declare(strict_types=1);

namespace NexiNets\Order;

use NexiNets\Administration\Model\ChargeItem;
use NexiNets\Administration\Model\RefundData;
use NexiNets\CheckoutApi\Api\ErrorCodeEnum;
use NexiNets\CheckoutApi\Api\Exception\InternalErrorPaymentApiException;
use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Request\PartialRefundCharge;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Charge;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Item;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Core\Content\NetsCheckout\Event\RefundChargeSend;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\Fetcher\PaymentFetcherInterface;
use NexiNets\Order\Exception\OrderChargeRefundExceeded;
use NexiNets\Order\Exception\OrderRefundException;
use NexiNets\RequestBuilder\RefundRequest;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class OrderRefund
{
    public function __construct(
        private readonly PaymentFetcherInterface $fetcher,
        private readonly PaymentApiFactory $apiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly RefundRequest $refundRequest,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws OrderChargeRefundExceeded
     * @throws OrderRefundException
     */
    public function fullRefund(OrderEntity $order): void
    {
        $transactions = $order->getTransactions();

        if (!$transactions instanceof OrderTransactionCollection) {
            throw new \LogicException('No order transactions found');
        }

        /** @var OrderTransactionEntity $transaction */
        foreach ($transactions as $transaction) {
            $paymentId = $transaction->getCustomFieldsValue(
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID
            );

            if ($paymentId === null) {
                continue;
            }

            $payment = $this->fetcher->fetchPayment($order->getSalesChannelId(), $paymentId);

            if ($payment->getStatus() !== PaymentStatusEnum::CHARGED) {
                continue;
            }

            $paymentApi = $this->createPaymentApi($order->getSalesChannelId());
            foreach ($payment->getCharges() as $charge) {
                $refundRequest = $this->refundRequest->buildFullRefund($charge);
                $this->logger->info('Full refund request', [
                    'paymentId' => $paymentId,
                    'refund' => $refundRequest,
                ]);

                try {
                    $response = $paymentApi->refundCharge(
                        $charge->getChargeId(),
                        $refundRequest
                    );
                } catch (PaymentApiException $e) {
                    $this->logger->error('Full refund failed', [
                        'paymentId' => $paymentId,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }

                $this->logger->info('Full refund success', [
                    'paymentId' => $paymentId,
                    'refundId' => $response->getRefundId(),
                ]);
            }

            $this->eventDispatcher->dispatch(
                new RefundChargeSend($order, $transaction, $transaction->getAmount()->getTotalPrice())
            );
        }
    }

    /**
     * @throws OrderChargeRefundExceeded
     * @throws OrderRefundException
     */
    public function partialRefund(OrderEntity $order, RefundData $refundData): void
    {
        $transactions = $order->getTransactions();

        if (!$transactions instanceof OrderTransactionCollection) {
            throw new \LogicException('No order transactions found');
        }

        /** @var OrderTransactionEntity $transaction */
        foreach ($transactions as $transaction) {
            $paymentId = $transaction->getCustomFieldsValue(
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID
            );

            if ($paymentId === null) {
                continue;
            }

            $payment = $this->fetcher->fetchPayment($order->getSalesChannelId(), $paymentId);
            $status = $payment->getStatus();

            if (
                !\in_array(
                    $status,
                    [
                        PaymentStatusEnum::PARTIALLY_REFUNDED,
                        PaymentStatusEnum::PARTIALLY_CHARGED,
                        PaymentStatusEnum::CHARGED,
                    ],
                    true
                )) {
                $this->logger->info('Payment in incorrect status for partial refund', [
                    'paymentId' => $paymentId,
                    'status' => $status->value,
                ]);

                continue;
            }

            $paymentApi = $this->createPaymentApi($order->getSalesChannelId());

            $alreadyRefunded = $transaction->getCustomFieldsValue(
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_REFUNDED
            );

            $charges = $refundData->getCharges();
            if ($charges === []) {
                $charges = $this->selectChargesForUnrelatedPartialRefund($payment, $refundData->getAmount(), $alreadyRefunded);
            }

            foreach ($charges as $chargeId => $items) {
                $partialRefund = $this->buildPartialRefund($transaction, $items);

                $this->logger->info('Partial refund request', [
                    'paymentId' => $paymentId,
                    'refund' => $partialRefund,
                ]);

                try {
                    $response = $paymentApi->refundCharge($chargeId, $partialRefund);
                    $transaction->changeCustomFields([
                        OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_REFUNDED => $alreadyRefunded + [
                            $chargeId => isset($alreadyRefunded[$chargeId])
                                ? $alreadyRefunded[$chargeId] + $partialRefund->getAmount()
                                : $partialRefund->getAmount(),
                        ],
                    ]);
                } catch (PaymentApiException $e) {
                    $this->logger->error('Partial refund failed', [
                        'paymentId' => $paymentId,
                        'error' => $e->getMessage(),
                    ]);

                    $this->throwCorrespondingOrderRefundException($e, $chargeId);

                    return;
                }

                $this->logger->info('Partial refund success', [
                    'paymentId' => $paymentId,
                    'refundId' => $response->getRefundId(),
                ]);
            }

            $this->eventDispatcher->dispatch(
                new RefundChargeSend($order, $transaction, $refundData->getAmount())
            );
        }
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->apiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId)
        );
    }

    /**
     * @param array<string, int> $alreadyRefunded
     *
     * @return array<string, array{amount: int, items: array<ChargeItem>}> $alreadyRefunded
     */
    private function selectChargesForUnrelatedPartialRefund(Payment $payment, float $refundAmount, array $alreadyRefunded): array
    {
        $refundAmount = (int) ($refundAmount * 100);
        $charges = $payment->getCharges() ?? [];

        // find charge matching refundAmount
        foreach ($charges as $charge) {
            if ($charge->getAmount() === $refundAmount && !isset($alreadyRefunded[$charge->getChargeId()])) {
                return [
                    $charge->getChargeId() => [
                        'amount' => $charge->getAmount(),
                        'items' => array_map(fn (Item $chargeItem): ChargeItem => new ChargeItem(
                            $charge->getChargeId(),
                            $chargeItem->getName(),
                            $chargeItem->getQuantity(),
                            $chargeItem->getUnit(),
                            $chargeItem->getUnitPrice(),
                            $chargeItem->getGrossTotalAmount(),
                            $chargeItem->getNetTotalAmount(),
                            $chargeItem->getReference(),
                            $chargeItem->getTaxRate(),
                        ), $charge->getOrderItems()),
                    ],
                ];
            }
        }

        // calculate refund per multiple charges
        $refunds = [];
        $remaining = $refundAmount;
        foreach ($charges as $charge) {
            $chargeAvailableAmount = isset($alreadyRefunded[$charge->getChargeId()])
                ? $charge->getAmount() - $alreadyRefunded[$charge->getChargeId()]
                : $charge->getAmount();

            if ($remaining === 0 || $chargeAvailableAmount === 0) {
                break;
            }

            $refundPerCharge = $chargeAvailableAmount;
            if ($chargeAvailableAmount <= $remaining) {
                $refundPerCharge = $chargeAvailableAmount;
                $remaining -= $chargeAvailableAmount;
            } elseif ($chargeAvailableAmount > $remaining) {
                $refundPerCharge = $remaining;
                $remaining = 0;
            }

            $refunds[$charge->getChargeId()] = [
                'amount' => $refundPerCharge,
                'items' => [],
            ];
        }

        return $refunds;
    }

    /**
     * @param array{amount: int, items: array<ChargeItem>} $items
     */
    public function buildPartialRefund(OrderTransactionEntity $transaction, array $items): PartialRefundCharge
    {
        if ($items['items'] === []) {
            return $this->refundRequest->buildUnrelatedPartialRefund(
                $items['amount']
            );
        }

        return $this->refundRequest->buildPartialRefund(
            $transaction,
            $items
        );
    }

    /**
     * @throws OrderChargeRefundExceeded
     * @throws OrderRefundException
     */
    private function throwCorrespondingOrderRefundException(
        PaymentApiException $exception,
        string $chargeId
    ): void {
        if (!$exception instanceof InternalErrorPaymentApiException) {
            throw new OrderRefundException($chargeId, previous: $exception);
        }

        throw match ($exception->getInternalCode()) {
            ErrorCodeEnum::InvalidRefundAmount => throw new OrderChargeRefundExceeded($chargeId, previous: $exception),
            default => throw new OrderRefundException($chargeId, previous: $exception)
        };
    }
}
