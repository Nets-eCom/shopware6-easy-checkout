<?php

declare(strict_types=1);

namespace Nexi\Checkout\Handler;

use Nexi\Checkout\Administration\Exception\WrongConfigurationException;
use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Locale\LanguageProvider;
use Nexi\Checkout\RequestBuilder\PaymentRequest;
use Nexi\Checkout\RequestBuilder\PaymentRequest\CheckoutBuilder;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\Exception\UnauthorizedApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use Psr\Log\LoggerInterface;
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
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

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
        private readonly LanguageProvider $languageProvider,
        private readonly OrderTransactionStateHandler $orderTransactionStateHandler,
        private readonly LoggerInterface $logger
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
        try {
            $paymentApi = $this->createPaymentApi($salesChannelId);
        } catch (WrongConfigurationException $wrongConfigurationException) {
            $this->logger->error('Hosted payment error - wrong configuration', [
                'transactionId' => $transactionId,
                'exception' => $wrongConfigurationException,
            ]);
            if ($request->hasSession() && $request->getSession() instanceof FlashBagAwareSessionInterface) {
                $request->getSession()->getFlashBag()->add(StorefrontController::DANGER, 'Wrong payment configuration - contact with shop administrator!');
            }

            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                $wrongConfigurationException->getMessage(),
                $wrongConfigurationException
            );
        }

        try {
            $paymentRequest = $this->paymentRequest->buildHosted(
                $transactionEntity,
                $salesChannelId,
                $transaction->getReturnUrl()
            );

            $payment = $paymentApi->createHostedPayment($paymentRequest);
        } catch (UnauthorizedApiException $unauthorizedApiException) {
            $this->logger->error('Hosted payment create unauthorized error', [
                'request' => $paymentRequest,
                'exception' => $unauthorizedApiException,
            ]);

            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                $unauthorizedApiException->getMessage(),
                $unauthorizedApiException
            );
        } catch (PaymentApiException $paymentApiException) {
            $this->logger->error('Hosted payment create error', [
                'request' => $paymentRequest,
                'exception' => $paymentApiException,
            ]);

            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                $paymentApiException->getMessage(),
                $paymentApiException
            );
        }

        $data = [
            'id' => $transactionId,
            'customFields' => [
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID => $payment->getPaymentId(),
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_ORDER => $paymentRequest->getOrder(),
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_REFUNDED => [],
            ],
        ];

        $this->orderTransactionRepository->update([$data], $context);

        $this->logger->info('Hosted payment created successfully', [
            'paymentId' => $payment->getPaymentId(),
        ]);

        return new RedirectResponse(\sprintf(
            '%s&language=%s',
            $payment->getHostedPaymentPageUrl(),
            $this->languageProvider->getLanguage($context)
        ));
    }

    public function finalize(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context
    ): void {
        $orderTransaction = $this->fetchOrderTransaction($transaction->getOrderTransactionId(), $context);
        $orderTransactionId = $orderTransaction->getId();
        $paymentId = $orderTransaction->getCustomFieldsValue(
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID
        );

        if ($request->query->getBoolean(CheckoutBuilder::CANCEL_PARAMETER_NAME)) {
            $this->logger->info('Hosted payment canceled by customer', [
                'paymentId' => $paymentId,
            ]);

            throw PaymentException::customerCanceled(
                $transaction->getOrderTransactionId(),
                'Customer was redirected to cancel url from Nexi payment'
            );
        }

        $this->logger->info('Hosted payment finalize started', [
            'paymentId' => $paymentId,
        ]);

        try {
            $paymentApi = $this->createPaymentApi($orderTransaction->getOrder()->getSalesChannelId());
        } catch (WrongConfigurationException $wrongConfigurationException) {
            $this->logger->error('Wrong plugin configuration', [
                'paymentId' => $paymentId,
                'exception' => $wrongConfigurationException,
            ]);
            throw PaymentException::asyncFinalizeInterrupted(
                $orderTransaction->getId(),
                'Couldn\'t finalize transaction',
                $wrongConfigurationException
            );
        }

        try {
            $payment = $paymentApi->retrievePayment((string) $paymentId)->getPayment();
        } catch (PaymentApiException $paymentApiException) {
            $this->logger->error('Hosted payment finalize error', [
                'paymentId' => $paymentId,
                'exception' => $paymentApiException,
            ]);

            throw PaymentException::asyncFinalizeInterrupted(
                $orderTransaction->getId(),
                'Couldn\'t finalize transaction',
                $paymentApiException
            );
        }

        if (!$this->canAuthorize($payment->getStatus())) {
            $this->logger->error('Hosted payment finalize can\'t authorize', [
                'payment' => $payment,
            ]);

            throw PaymentException::asyncFinalizeInterrupted($orderTransactionId, 'Couldn\'t finalize transaction');
        }

        try {
            $this->orderTransactionStateHandler->authorize($orderTransactionId, $context);
        } catch (IllegalTransitionException $illegalTransitionException) {
            $this->logger->error('Hosted payment finalize illegal transition', [
                'transactionState' => $this->fetchStateTechnicalName($orderTransactionId, $context),
                'paymentId' => $paymentId,
                'exception' => $illegalTransitionException,
            ]);
        }

        $this->logger->info('Hosted payment finalized successfully', [
            'paymentId' => $paymentId,
        ]);
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
            ->addAssociation('order.deliveries.shippingMethod')
            ->addAssociation('order.deliveries.shippingOrderAddress.country')
            ->addAssociation('order.billingAddress.country');

        /** @var OrderTransactionEntity|null $orderTransaction */
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction === null) {
            throw PaymentException::invalidTransaction($orderTransactionId);
        }

        return $orderTransaction;
    }

    /**
     * @throws WrongConfigurationException
     */
    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        $secretKey = $this->configurationProvider->getSecretKey($salesChannelId);
        if ($secretKey === '') {
            throw new WrongConfigurationException(\sprintf('SecretKey is missing for channelId %s', $salesChannelId));
        }

        return $this->paymentApiFactory->create(
            $secretKey,
            $this->configurationProvider->isLiveMode($salesChannelId),
        );
    }

    private function canAuthorize(PaymentStatusEnum $paymentStatus): bool
    {
        return $paymentStatus === PaymentStatusEnum::RESERVED || $paymentStatus === PaymentStatusEnum::CHARGED;
    }

    private function fetchStateTechnicalName(string $orderTransactionId, Context $context): string
    {
        $criteria = (new Criteria([$orderTransactionId]))
                ->addAssociation('stateMachineState');

        /** @var OrderTransactionEntity|null $orderTransaction */
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction === null) {
            throw PaymentException::invalidTransaction($orderTransactionId);
        }

        return $orderTransaction->getStateMachineState()->getTechnicalName();
    }
}
