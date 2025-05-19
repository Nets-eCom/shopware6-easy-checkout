<?php declare(strict_types=1);

namespace Nexi\Checkout\Subscriber;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Handler\EmbeddedPayment;
use Nexi\Checkout\Locale\LanguageProvider;
use Nexi\Checkout\RequestBuilder\PaymentRequest;
use Nexi\Checkout\Struct\TransactionDetailsStruct;
use NexiCheckout\Api\Exception\ClientErrorPaymentApiException;
use NexiCheckout\Api\Exception\InternalErrorPaymentApiException;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\RouterInterface;

class EmbeddedCreatePaymentOnCheckoutSubscriber implements EventSubscriberInterface
{
    public const SESSION_NEXI_PAYMENT_ORDER = 'nexi_payment_order';

    public function __construct(
        private readonly PaymentRequest $paymentRequest,
        private readonly PaymentApiFactory $paymentApiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly LanguageProvider $languageProvider,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        private readonly string $liveCheckoutJsUrl,
        private readonly string $testCheckoutJsUrl,
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
        $salesChannelContext = $event->getSalesChannelContext();
        $paymentMethod = $salesChannelContext->getPaymentMethod();

        if ($paymentMethod->getHandlerIdentifier() !== EmbeddedPayment::class) {
            return;
        }

        $page = $event->getPage();
        $cart = $page->getCart();

        $paymentId = $this->createPayment($cart, $salesChannelContext);

        $transaction = $cart->getTransactions()->first();

        if ($transaction === null || $transaction->getPaymentMethodId() !== $paymentMethod->getId()) {
            return;
        }

        $page->addExtension(
            'nexiTransactionDetails',
            $this->buildTransactionDetailsStruct(
                $paymentId,
                $salesChannelContext->getSalesChannelId(),
                $salesChannelContext->getContext(),
            )
        );
    }

    /**
     * @throws PaymentApiException|\JsonException
     */
    private function createPayment(
        Cart $cart,
        SalesChannelContext $salesChannelContext
    ): ?string {
        $paymentApi = $this->createPaymentApi($salesChannelContext->getSalesChannelId());

        try {
            $paymentRequest = $this->paymentRequest->buildEmbedded(
                $cart,
                $salesChannelContext,
            );

            $payment = $paymentApi->createEmbeddedPayment($paymentRequest);

            $session = $this->requestStack->getCurrentRequest()->getSession();
            $session->set(self::SESSION_NEXI_PAYMENT_ORDER, $paymentRequest->getOrder());

            $this->logger->info('Embedded payment created successfully', [
                'paymentId' => $payment->getPaymentId(),
            ]);
        } catch (ClientErrorPaymentApiException $paymentApiException) {
            $this->logger->error('Embedded payment create client error', [
                'request' => $paymentRequest,
                'exception' => $paymentApiException,
            ]);

            foreach ($this->flattenedMessages($paymentApiException->getErrors()) as $message) {
                $this->addFlash(
                    StorefrontController::DANGER,
                    $message
                );
            }
        } catch (InternalErrorPaymentApiException $paymentApiException) {
            $this->logger->error('Embedded payment create internal error', [
                'request' => $paymentRequest,
                'exception' => $paymentApiException,
            ]);

            $this->addFlash(StorefrontController::DANGER, $paymentApiException->getInternalMessage());
        } catch (PaymentApiException $paymentApiException) {
            $this->logger->error('Embedded payment create undefined error', [
                'request' => $paymentRequest,
                'exception' => $paymentApiException,
            ]);

            $this->addFlash(StorefrontController::DANGER, $paymentApiException->getMessage());
        } finally {
            $paymentId = isset($payment) ? $payment->getPaymentId() : null;
        }

        return $paymentId;
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId),
        );
    }

    private function buildTransactionDetailsStruct(
        ?string $paymentId,
        string $salesChannelId,
        Context $context,
    ): TransactionDetailsStruct {
        return new TransactionDetailsStruct(
            $paymentId,
            $this->configurationProvider->getCheckoutKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId)
                ? $this->liveCheckoutJsUrl
                : $this->testCheckoutJsUrl,
            $this->languageProvider->getLanguage($context),
            $this->router->generate('nexicheckout_payment.nexicheckout.embedded.handle-payment'),
        );
    }

    private function addFlash(string $type, mixed $message): void
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();
        if (!$session instanceof FlashBagAwareSessionInterface) {
            return;
        }

        $session->getFlashBag()->add($type, $message);
    }

    /**
     * @param array<list<string>> $errors
     *
     * @return list<non-empty-string>
     */
    private function flattenedMessages(array $errors): array
    {
        $flatErrors = [];

        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $flatErrors[] = \sprintf('%s: %s', $field, $message);
            }
        }

        return $flatErrors;
    }
}
