<?php declare(strict_types=1);

namespace NexiNets\Administration\Controller;

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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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

    #[Route(path: '/api/order/{orderId}/nexinets-payment-detail', name: 'api.nexinets.payment.detail', defaults: [
        '_acl' => ['order:read'],
    ], methods: ['GET'])]
    public function getPaymentDetail(string $orderId, Request $request, Context $context): Response
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

        $remainingCharge = $payment->getOrderDetails()->getAmount() - $payment->getSummary()->getChargedAmount();
        $remainingRefund = $payment->getSummary()->getChargedAmount() - $payment->getSummary()->getRefundedAmount();

        return new JsonResponse([
            'paymentId' => $paymentId,
            'paymentVia' => $payment->getPaymentDetails()?->getPaymentMethod(),
            'orderAmount' => $this->formatAmount($payment->getOrderDetails()->getAmount()),
            'orderTime' => $payment->getCreated()->format('Y-m-d H:i:s'),
            'chargedAmount' => $this->formatAmount($payment->getSummary()->getChargedAmount()),
            'remainingCharge' => $this->formatAmount($remainingCharge),
            'refundedAmount' => $this->formatAmount($payment->getSummary()->getRefundedAmount()),
            'remainingRefund' => $this->formatAmount($remainingRefund),
            'status' => $payment->getStatus()->value,
        ]);
    }

    private function formatAmount(?int $amount): string
    {
        return number_format((int) $amount / 100, 2);
    }
}
