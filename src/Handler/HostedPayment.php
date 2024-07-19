<?php

declare(strict_types=1);

namespace NexiNets\Handler;

use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Request\Payment\IntegrationTypeEnum;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\NetsCheckout;
use NexiNets\RequestBuilder\PaymentRequest;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final readonly class HostedPayment implements AsynchronousPaymentHandlerInterface
{
    private const ORDER_TRANSACTION_CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID = 'nexi_nets_payment_id';

    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(
        private PaymentRequest $paymentRequest,
        private PaymentApiFactory $paymentApiFactory,
        private ConfigurationProvider $configurationProvider,
        private EntityRepository $orderTransactionRepository,
        private string $shopwareVersion
    ) {
    }

    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {
        $paymentApi = $this->createPaymentApi($salesChannelContext);
        $transactionId = $transaction->getOrderTransaction()->getId();

        try {
            $payment = $paymentApi->createPayment(
                $this->paymentRequest->build(
                    $transaction,
                    $salesChannelContext,
                    IntegrationTypeEnum::HostedPaymentPage
                )
            );
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
                self::ORDER_TRANSACTION_CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID => $payment->getPaymentId(),
            ],
        ];

        $this->orderTransactionRepository->update([$data], $salesChannelContext->getContext());

        // TODO: return url with language query parameter
        return new RedirectResponse($payment->getHostedPaymentPageUrl());
    }

    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
        // TODO
    }

    private function createPaymentApi(SalesChannelContext $salesChannelContext): PaymentApi
    {
        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelContext->getSalesChannelId()),
            $this->configurationProvider->isLiveMode($salesChannelContext->getSalesChannelId()),
            $this->getCommercePlatformTag()
        );
    }

    private function getCommercePlatformTag(): string
    {
        return sprintf(
            '%s %s, %s, php%s',
            NetsCheckout::COMMERCE_PLATFORM_TAG,
            $this->shopwareVersion,
            NetsCheckout::PLUGIN_VERSION,
            \PHP_VERSION
        );
    }
}
