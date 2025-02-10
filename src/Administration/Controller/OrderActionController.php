<?php

declare(strict_types=1);

namespace Nexi\Checkout\Administration\Controller;

use Nexi\Checkout\Administration\Exception\OrderActionHttpException;
use Nexi\Checkout\Administration\Model\ChargeData;
use Nexi\Checkout\Administration\Model\RefundData;
use Nexi\Checkout\Order\Exception\OrderCancelException;
use Nexi\Checkout\Order\Exception\OrderChargeException;
use Nexi\Checkout\Order\Exception\OrderChargeRefundExceeded;
use Nexi\Checkout\Order\Exception\OrderRefundException;
use Nexi\Checkout\Order\OrderCancel;
use Nexi\Checkout\Order\OrderCharge;
use Nexi\Checkout\Order\OrderRefund;
use NexiCheckout\Api\Exception\PaymentApiException;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [
    '_routeScope' => ['api'],
])]
class OrderActionController extends AbstractController
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly OrderCharge $orderCharge,
        private readonly OrderRefund $orderRefund,
        private readonly OrderCancel $orderCancel
    ) {
    }

    #[
        Route(
            path: '/api/order/{orderId}/nexi-payment-charge',
            name: 'api.nexi.payment.charge',
            defaults: [
                '_acl' => [
                    'order:read',
                    'order:write',
                ],
            ],
            methods: ['PUT']
        )
    ]
    public function charge(
        Context $context,
        string $orderId,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ChargeData $chargeData
    ): Response {
        $order = $this->orderRepository->search(
            (new Criteria([$orderId]))
                ->addAssociation('transactions.order')
                ->addAssociation('stateMachineState'),
            $context
        )->get($orderId);

        if (!$order instanceof OrderEntity) {
            throw OrderException::orderNotFound($orderId);
        }

        try {
            $this->processCharge($order, $chargeData);
        } catch (OrderChargeException $orderChargeException) {
            throw OrderActionHttpException::chargeFailed($orderChargeException->getPaymentId());
        }

        return $this->json([]);
    }

    #[
        Route(
            path: '/api/order/{orderId}/nexi-payment-refund',
            name: 'api.nexi.payment.refund',
            defaults: [
                '_acl' => [
                    'order:read',
                    'order:write',
                ],
            ],
            methods: ['PUT']
        )
    ]
    public function refund(
        Context $context,
        string $orderId,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        RefundData $refundData
    ): Response {
        $order = $this->orderRepository->search(
            (new Criteria([$orderId]))
                ->addAssociation('transactions')
                ->addAssociation('stateMachineState'),
            $context
        )->get($orderId);

        if (!$order instanceof OrderEntity) {
            throw OrderException::orderNotFound($orderId);
        }

        $refundData->setContext($context);

        try {
            $this->processRefund($order, $refundData);
        } catch (OrderRefundException $orderRefundException) {
            $this->resolveToHttpException($orderRefundException);
        }

        return $this->json([]);
    }

    #[
        Route(
            path: '/api/order/{orderId}/nexi-payment-cancel',
            name: 'api.nexi.payment.cancel',
            defaults: [
                '_acl' => [
                    'order:read',
                    'order:write',
                ],
            ],
            methods: ['PUT']
        )
    ]
    public function cancel(Context $context, string $orderId): Response
    {
        $order = $this->orderRepository->search(
            (new Criteria([$orderId]))
                ->addAssociation('transactions')
                ->addAssociation('stateMachineState'),
            $context
        )->get($orderId);

        if (!$order instanceof OrderEntity) {
            throw OrderException::orderNotFound($orderId);
        }

        try {
            $this->orderCancel->cancel($order);
        } catch (OrderCancelException $orderCancelException) {
            throw OrderActionHttpException::cancelFailed($orderCancelException->getPaymentId());
        }

        return $this->json([]);
    }

    /**
     * @throws PaymentApiException
     * @throws OrderChargeException
     */
    private function processCharge(OrderEntity $order, ChargeData $chargeData): void
    {
        if ($chargeData->getAmount() < $order->getAmountTotal()) {
            $this->orderCharge->partialCharge($order, $chargeData);

            return;
        }

        $this->orderCharge->fullCharge($order);
    }

    /**
     * @throws OrderChargeRefundExceeded
     * @throws OrderRefundException
     */
    private function processRefund(OrderEntity $order, RefundData $refundData): void
    {
        if ($refundData->getAmount() < $order->getAmountTotal()) {
            $this->orderRefund->partialRefund($order, $refundData);

            return;
        }

        $this->orderRefund->fullRefund($order);
    }

    private function resolveToHttpException(OrderRefundException $exception): void
    {
        $chargeId = $exception->getChargeId();

        if (!$exception instanceof OrderChargeRefundExceeded) {
            throw OrderActionHttpException::refundFailed($chargeId);
        }

        throw OrderActionHttpException::refundAmountExceeded($chargeId);
    }
}
