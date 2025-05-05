<?php

declare(strict_types=1);

namespace Nexi\Checkout\Handler;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\RequestBuilder\Helper\FormatHelper;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class EmbeddedPayment extends AbstractPaymentHandler
{
    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(
        private readonly PaymentApiFactory $paymentApiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly FormatHelper $formatHelper,
        private readonly EntityRepository $orderTransactionRepository,
        private readonly OrderTransactionStateHandler $orderTransactionStateHandler,
        private readonly LoggerInterface $logger
    ) {
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return $type !== PaymentHandlerType::RECURRING;
    }

    public function validate(
        Cart $cart,
        RequestDataBag $dataBag,
        SalesChannelContext $context
    ): ?Struct {
        $paymentId = $dataBag->getString('nexiPaymentId');
        if ($paymentId === '') {
            $this->logger->error('Embedded payment nexiPaymentId missing', [
                'cart' => $cart,
                'dataBag' => $dataBag,
            ]);

            throw PaymentException::validatePreparedPaymentInterrupted(
                'Transaction details not found'
            );
        }

        $paymentApi = $this->createPaymentApi($context->getSalesChannelId());
        try {
            $payment = $paymentApi->retrievePayment($paymentId)->getPayment();
        } catch (PaymentApiException $paymentApiException) {
            $this->logger->error('Embedded payment validate error', [
                'paymentId' => $paymentId,
                'exception' => $paymentApiException,
            ]);

            throw PaymentException::validatePreparedPaymentInterrupted(
                'Couldn\'t validate payment',
                $paymentApiException
            );
        }

        if ($cart->getToken() !== $payment->getMyReference()) {
            $this->logger->error('Embedded payment validate token mismatch', [
                'paymentId' => $paymentId,
                'cart' => $cart,
            ]);

            throw PaymentException::validatePreparedPaymentInterrupted(
                'Payment token does not match cart token'
            );
        }

        $cartPrice = $this->formatHelper->priceToInt($cart->getPrice()->getTotalPrice());
        if ($cartPrice !== $payment->getOrderDetails()->getAmount()) {
            $this->logger->error('Embedded payment validate price mismatch', [
                'paymentId' => $paymentId,
                'cart' => $cart,
            ]);

            throw PaymentException::validatePreparedPaymentInterrupted(
                'Payment amount does not match cart amount'
            );
        }

        $this->logger->info('Embedded payment validated successfully', [
            'paymentId' => $paymentId,
        ]);

        // return the payment details: these will be given as $validateStruct to the pay method
        return new ArrayStruct([
            'paymentId' => $payment->getPaymentId(),
        ]);
    }

    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): null {
        $transactionId = $transaction->getOrderTransactionId();

        if (!$validateStruct instanceof ArrayStruct) {
            throw PaymentException::capturePreparedException(
                $transactionId,
                'validateStruct not found'
            );
        }

        $paymentId = $validateStruct->get('paymentId');

        $data = [
            'id' => $transactionId,
            'customFields' => [
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID => $paymentId,
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_ORDER => [], // @TODO: add order from createPayment request
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_REFUNDED => [],
            ],
        ];

        $this->orderTransactionRepository->update([$data], $context);

        $this->orderTransactionStateHandler->process(
            $transactionId,
            $context
        );

        $this->logger->info('Embedded payment created successfully', [
            'paymentId' => $paymentId,
        ]);

        return null;
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId),
        );
    }
}
