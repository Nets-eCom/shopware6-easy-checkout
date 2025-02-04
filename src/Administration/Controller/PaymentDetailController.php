<?php declare(strict_types=1);

namespace Nexi\Checkout\Administration\Controller;

use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Fetcher\CachablePaymentFetcherInterface;
use NexiCheckout\Model\Request\Item as RequestItem;
use NexiCheckout\Model\Result\RetrievePayment\Charge;
use NexiCheckout\Model\Result\RetrievePayment\Item;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @phpstan-import-type RequestItemSerialized from RequestItem
 */
#[Route(defaults: [
    '_routeScope' => ['api'],
])]
class PaymentDetailController extends AbstractController
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly CachablePaymentFetcherInterface $paymentFetcher,
    ) {
    }

    #[Route(
        path: '/api/order/{orderId}/nexi-payment-detail',
        name: 'api.nexicheckout.payment.detail',
        defaults: [
            '_acl' => ['order:read'],
        ],
        methods: ['GET']
    )]
    public function getPaymentDetail(string $orderId, Context $context): Response
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('transactions')
            ->addAssociation('stateMachineState');

        /** @var ?OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->get($orderId);

        if (!$order) {
            throw OrderException::orderNotFound($orderId);
        }

        // @todo handle multiple transactions per order
        $transaction = $order->getTransactions()->firstWhere(
            fn (OrderTransactionEntity $transaction) => $transaction->getCustomFieldsValue(OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID) !== null
        );

        if (!$transaction) {
            throw OrderException::orderTransactionNotFound($orderId);
        }

        $paymentId = $transaction->getCustomFieldsValue(OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID);
        $payment = $this->paymentFetcher->getCachedPayment($order->getSalesChannelId(), $paymentId);

        $summary = $payment->getSummary();
        $orderDetails = $payment->getOrderDetails();
        $orderAmount = $orderDetails->getAmount();
        $chargedAmount = $summary->getChargedAmount();
        $refundedAmount = $summary->getRefundedAmount();
        $status = $payment->getStatus();

        $remainingChargeAmount = $status !== PaymentStatusEnum::CANCELLED ? $orderAmount - $chargedAmount : 0;
        $remainingRefundAmount = $status !== PaymentStatusEnum::CANCELLED ? $chargedAmount - $refundedAmount : 0;

        return new JsonResponse([
            'paymentId' => $paymentId,
            'paymentVia' => $payment->getPaymentDetails()?->getPaymentMethod(),
            'orderAmount' => $this->formatAmount($orderAmount),
            'orderTime' => $payment->getCreated()->format('Y-m-d H:i:s'),
            'chargedAmount' => $this->formatAmount($chargedAmount),
            'remainingChargeAmount' => $this->formatAmount($remainingChargeAmount),
            'refundedAmount' => $this->formatAmount($refundedAmount),
            'remainingRefundAmount' => $this->formatAmount($remainingRefundAmount),
            'status' => $status->value,
            'orderItems' => $this->buildItems($payment, $transaction),
            'charges' => $this->buildChargedItems($payment),
        ]);
    }

    /**
     * @return list<array{
     *      name: string,
     *      reference: string,
     *      quantity: int|float,
     *      unitPrice: string,
     *      grossTotalAmount: string,
     *      qtyRefunded: int
     * }>
     */
    private function buildChargedItems(Payment $payment): array
    {
        $charges = $payment->getCharges();

        if ($charges === null) {
            return [];
        }

        $chargedItems = [];

        foreach ($charges as $charge) {
            foreach ($charge->getOrderItems() as $chargedItem) {
                $chargedItems[] = [
                    'chargeId' => $charge->getChargeId(),
                    'name' => $chargedItem->getName(),
                    'unit' => $chargedItem->getUnit(),
                    'quantity' => $chargedItem->getQuantity(),
                    'unitPrice' => $this->formatAmount($chargedItem->getUnitPrice()),
                    'grossTotalAmount' => $this->formatAmount($chargedItem->getGrossTotalAmount()),
                    'netTotalAmount' => $this->formatAmount($chargedItem->getNetTotalAmount()),
                    'reference' => $chargedItem->getReference(),
                    'taxRate' => $chargedItem->getTaxRate() !== null ? $this->formatAmount($chargedItem->getTaxRate()) : null,
                    'qtyRefunded' => 0, // @todo remove?
                ];
            }
        }

        return $chargedItems;
    }

    private function formatAmount(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }

    /**
     * @return list<array{
     *      name: string,
     *      reference: string,
     *      quantity: int,
     *      unitPrice: string,
     *      grossTotalAmount: string,
     *      qtyCharged: int
     * }>
     */
    private function buildItems(Payment $payment, OrderTransactionEntity $transaction): array
    {
        $orderArray = $transaction->getCustomFieldsValue(OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_ORDER);

        return array_map(
            fn (array $requestItem) => [
                'reference' => $requestItem['reference'],
                'name' => $requestItem['name'],
                'quantity' => $requestItem['quantity'],
                'unitPrice' => $this->formatAmount($requestItem['unitPrice']),
                'grossTotalAmount' => $this->formatAmount($requestItem['grossTotalAmount']),
                'qtyCharged' => array_reduce(
                    $payment->getCharges() ?? [],
                    fn (int $chargeQty, Charge $charge) => $chargeQty + array_reduce(
                        $charge->getOrderItems(),
                        fn (int $requestItemQty, Item $chargedItem) => $requestItemQty + ($this->isSameItem($chargedItem, $requestItem) ? $chargedItem->getQuantity() : 0),
                        0
                    ),
                    0
                ),
            ],
            $orderArray['items'] ?? []
        );
    }

    /**
     * @param RequestItemSerialized $item
     */
    private function isSameItem(Item $orderItem, array $item): bool
    {
        return $orderItem->getReference() === $item['reference'] && $orderItem->getName() === $item['name'];
    }
}
