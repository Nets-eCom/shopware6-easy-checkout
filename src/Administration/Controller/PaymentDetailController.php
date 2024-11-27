<?php declare(strict_types=1);

namespace NexiNets\Administration\Controller;

use NexiNets\CheckoutApi\Model\Request\Item as RequestItem;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Charge;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Item;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Refund;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\Fetcher\PaymentFetcher;
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
        private readonly PaymentFetcher $paymentFetcher,
    ) {
    }

    #[Route(
        path: '/api/order/{orderId}/nexinets-payment-detail',
        name: 'api.nexinets.payment.detail',
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
            fn (OrderTransactionEntity $transaction) => $transaction->getCustomFieldsValue(OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID) !== null
        );

        if (!$transaction) {
            throw OrderException::orderTransactionNotFound($orderId);
        }

        $paymentId = $transaction->getCustomFieldsValue(OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID);
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
        ]);
    }

    private function formatAmount(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }

    /**
     * @return list<array{
     *      name: string,
     *      quantity: int,
     *      unitPrice: string,
     *      grossTotalAmount: string,
     *      qtyCharged: int,
     *      qtyRefunded: int
     * }>
     */
    private function buildItems(Payment $payment, OrderTransactionEntity $transaction): array
    {
        $orderArray = $transaction->getCustomFieldsValue(OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_ORDER);

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
                'qtyRefunded' => array_reduce(
                    $payment->getRefunds() ?? [],
                    fn (int $refundQty, Refund $refund) => $refundQty + array_reduce(
                        $refund->getOrderItems(),
                        fn (int $requestItemQty, Item $refundedItem) => $requestItemQty + ($this->isSameItem($refundedItem, $requestItem) ? $refundedItem->getQuantity() : 0),
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
