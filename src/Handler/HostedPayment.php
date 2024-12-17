<?php

declare(strict_types=1);

namespace NexiNets\Handler;

use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Request\Payment\IntegrationTypeEnum;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Summary;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\RequestBuilder\PaymentRequest;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class HostedPayment extends AbstractPaymentHandler
{
    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(
        private readonly PaymentRequest $paymentRequest,
        private readonly PaymentApiFactory $paymentApiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly EntityRepository $orderTransactionRepository,
        private readonly OrderTransactionStateHandler $orderTransactionStateHandler
    ) {
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return $type !== PaymentHandlerType::RECURRING;
    }

    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): RedirectResponse {
        $transactionId = $transaction->getOrderTransactionId();
        $transactionEntity = $this->fetchOrderTransaction($transactionId, $context);
        $salesChannelId = $transactionEntity->getOrder()->getSalesChannelId();
        $paymentApi = $this->createPaymentApi($salesChannelId);

        try {
            $paymentRequest = $this->paymentRequest->build(
                $transactionEntity,
                $salesChannelId,
                $transaction->getReturnUrl(),
                IntegrationTypeEnum::HostedPaymentPage
            );

            $payment = $paymentApi->createPayment($paymentRequest);
        } catch (PaymentApiException $paymentApiException) {
            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                $paymentApiException->getMessage(),
                $paymentApiException
            );
        }

        $data = [
            'id' => $transactionId,
            'customFields' => [
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID => $payment->getPaymentId(),
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_ORDER => $paymentRequest->getOrder(),
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_REFUNDED => [],
            ],
        ];

        $this->orderTransactionRepository->update([$data], $context);

        // TODO: return url with language query parameter
        return new RedirectResponse($payment->getHostedPaymentPageUrl());
    }

    public function finalize(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context
    ): void {
        $orderTransaction = $this->fetchOrderTransaction($transaction->getOrderTransactionId(), $context);
        $orderTransactionId = $orderTransaction->getId();
        $paymentId = $orderTransaction->getCustomFieldsValue(
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID
        );

        $paymentApi = $this->createPaymentApi($orderTransaction->getOrder()->getSalesChannelId());

        try {
            $payment = $paymentApi->retrievePayment($paymentId)->getPayment();
        } catch (PaymentApiException $paymentApiException) {
            throw PaymentException::asyncFinalizeInterrupted(
                $orderTransaction->getId(),
                'Couldn\'t finalize transaction',
                $paymentApiException
            );
        }

        $summary = $payment->getSummary();

        if (!$this->canAuthorize($summary)) {
            throw PaymentException::asyncFinalizeInterrupted($orderTransactionId, 'Couldn\'t finalize transaction');
        }

        $this->orderTransactionStateHandler->authorize($orderTransactionId, $context);
    }

    public function refund(
        RefundPaymentTransactionStruct $transaction,
        Context $context
    ): void {
        throw PaymentException::paymentHandlerTypeUnsupported($this, PaymentHandlerType::REFUND);
    }

    private function fetchOrderTransaction(string $orderTransactionId, Context $context): OrderTransactionEntity
    {
        $criteria = (new Criteria([$orderTransactionId]))
            ->addAssociation('order')
            ->addAssociation('order.currency')
            ->addAssociation('order.lineItems')
            ->addAssociation('order.deliveries.shippingOrderAddress.country')
            ->addAssociation('order.billingAddress.country')
        ;
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction === null) {
            throw PaymentException::invalidTransaction($orderTransactionId);
        }

        return $orderTransaction;
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId),
        );
    }

    private function canAuthorize(Summary $summary): bool
    {
        return $summary->getReservedAmount() > 0;
    }
}
