<?php declare(strict_types=1);

namespace Nexi\Checkout\Subscriber;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Handler\EmbeddedPayment;
use Nexi\Checkout\RequestBuilder\PaymentRequest;
use Nexi\Checkout\Struct\TransactionDetailsStruct;
use NexiCheckout\Api\Exception\ClientErrorPaymentApiException;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class EmbeddedCreatePaymentOnCheckoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PaymentRequest $paymentRequest,
        private readonly PaymentApiFactory $paymentApiFactory,
        private readonly ConfigurationProvider $configurationProvider,
    ) {
    }

    /**
     * @return array|string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmLoaded',
        ];
    }

    public function onCheckoutConfirmLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $cart = $event->getPage()->getCart();
        $request = $event->getRequest();
        $salesChannelContext = $event->getSalesChannelContext();
        $paymentMethod = $salesChannelContext->getPaymentMethod();

        if ($paymentMethod->getHandlerIdentifier() !== EmbeddedPayment::class) {
            return;
        }

        try {
            $paymentId = $this->createPayment($cart, $salesChannelContext, $request);
        } catch (ClientErrorPaymentApiException|PaymentApiException $exception) {
        } finally {
            // no exception should be thrown because we want payment window to be rendered with error
            $paymentId = $paymentId ?? null;
        }

        $transactionDetails = new TransactionDetailsStruct($paymentId);

        $transaction = $cart->getTransactions()->first();
        if ($transaction === null || $transaction->getPaymentMethodId() !== $paymentMethod->getId()) {
            return;
        }

        $event->getPage()->addExtension('nexiTransactionDetails', $transactionDetails);
    }

    /**
     * @throws PaymentApiException
     */
    private function createPayment(
        Cart $cart,
        SalesChannelContext $salesChannelContext,
        Request $request
    ): string {
        $paymentId = $request->query->getString('paymentId');

        if ($this->isExistingPaymentValid($paymentId)) {
            return $paymentId;
        }

        $paymentRequest = $this->paymentRequest->buildEmbedded(
            $cart,
            $salesChannelContext,
            $request->getUri()
        );

        $paymentApi = $this->createPaymentApi($salesChannelContext->getSalesChannelId());
        $payment = $paymentApi->createEmbeddedPayment($paymentRequest);

        return $payment->getPaymentId();
    }

    private function isExistingPaymentValid(string $paymentId): bool
    {
        // @TODO: implement validation logic for payment that are jumping out of iframe
        return $paymentId !== '';
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId),
        );
    }
}
