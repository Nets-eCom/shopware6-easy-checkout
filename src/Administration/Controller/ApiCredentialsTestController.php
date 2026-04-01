<?php

declare(strict_types=1);

namespace Nexi\Checkout\Administration\Controller;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\NetsNexiCheckout;
use NexiCheckout\Api\Exception\UnauthorizedApiException;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Factory\Provider\HttpClientConfigurationProvider;
use NexiCheckout\Model\Request\Item;
use NexiCheckout\Model\Request\Payment;
use NexiCheckout\Model\Request\Payment\HostedCheckout;
use NexiCheckout\Model\Request\Shared\Order;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route(defaults: [
    '_routeScope' => ['api'],
])]
final class ApiCredentialsTestController extends AbstractController
{
    private const TEST_AMOUNT = 1;

    private const DEF_CURRENCY = 'EUR';

    /**
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     */
    public function __construct(
        private readonly PaymentApiFactory $paymentApiFactory,
        private readonly HttpClientConfigurationProvider $configurationProvider,
        private readonly EntityRepository $currencyRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/api/nexicheckout/configuration/checkout-url-info',
        name: 'api.nexicheckout.configuration.checkout_url_info',
        methods: ['GET']
    )]
    public function showCheckoutUrlInfo(Request $request, Context $context): JsonResponse
    {
        $config = $this->configurationProvider->provide('', true);

        return new JsonResponse([
            'showMessage' => $config->getBaseUrl() !== NetsNexiCheckout::LIVE_URL,
        ]);
    }

    #[Route(
        path: '/api/nexicheckout/test-api-credentials',
        name: 'api.nexicheckout.credentials.test',
        defaults: [
            '_acl' => [
                'order:read',
                'order:write',
            ],
        ],
        methods: ['POST']
    )]
    public function test(Request $request, Context $context): JsonResponse
    {
        $secretKey = $request->request->getString('secretKey');
        $liveMode = $request->request->getBoolean('liveMode');
        $salesChannelId = $request->request->getString('salesChannelId');

        if ($secretKey === '' && $salesChannelId !== '') {
            $secretKey = (string) $this->systemConfigService->get(
                $liveMode ? ConfigurationProvider::LIVE_SECRET_KEY : ConfigurationProvider::TEST_SECRET_KEY
            );
            $this->logger->info('API credentials get from system config', [
                'salesChannelId' => $salesChannelId,
                'liveMode' => $liveMode ? 'true' : 'false',
            ]);
        }

        if ($secretKey === '') {
            $this->logger->info('API credentials test failed: secret key is empty.', [
                'salesChannelId' => $salesChannelId,
                'liveMode' => $liveMode ? 'true' : 'false',
            ]);

            return new JsonResponse([
                'valid' => false,
                'message' => 'Secret key is empty. Please fill in the key field and try again.',
            ]);
        }

        try {
            $paymentApi = $this->paymentApiFactory->create($secretKey, $liveMode);
            $currencyIsoCode = $this->resolveDefaultCurrencyIsoCode($context);
            $payment = $paymentApi->createHostedPayment($this->buildProbePayment($currencyIsoCode));
            if ($payment->getPaymentId() !== '' && $payment->getPaymentId() !== '0') {
                $paymentApi->terminate($payment->getPaymentId());
            }

            return new JsonResponse([
                'valid' => true,
                'message' => 'API credentials are valid.',
            ]);
        } catch (UnauthorizedApiException) {
            $this->logger->error('API credentials test failed: API secretKey are invalid', [
                'salesChannelId' => $salesChannelId,
                'liveMode' => $liveMode ? 'true' : 'false',
            ]);

            return new JsonResponse([
                'valid' => false,
                'message' => 'API credentials are invalid.',
            ]);
        } catch (\Throwable $throwable) {
            $this->logger->error('API credentials check failed', [
                'message' => $throwable->getMessage(),
                'exception' => $throwable,
            ]);

            // Any non-401 response means API accepted the credentials;
            // transport / other API errors are inconclusive but not an auth failure.
            if (!$throwable->getPrevious() instanceof \Throwable) {
                return new JsonResponse([
                    'valid' => true,
                    'message' => 'API credentials look valid.',
                ]);
            }

            return new JsonResponse([
                'valid' => false,
                'message' => 'Credential check failed due to API connectivity issues.',
            ]);
        }
    }

    private function resolveDefaultCurrencyIsoCode(Context $context): string
    {
        $criteria = new Criteria([Defaults::CURRENCY]);
        $currency = $this->currencyRepository->search($criteria, $context)->first();

        return $currency?->getIsoCode() ?? self::DEF_CURRENCY;
    }

    private function buildProbePayment(string $currencyIsoCode): Payment
    {
        $item = new Item(
            name: 'Credential check item',
            quantity: 1,
            unit: 'pcs',
            unitPrice: self::TEST_AMOUNT,
            grossTotalAmount: self::TEST_AMOUNT,
            netTotalAmount: self::TEST_AMOUNT,
            reference: 'nexi-credential-check',
        );

        $order = new Order(
            items: [$item],
            currency: $currencyIsoCode,
            amount: self::TEST_AMOUNT,
        );

        $url = $this->generateUrl('frontend.home.page', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $checkout = new HostedCheckout(
            returnUrl: $url,
            cancelUrl: $url,
            termsUrl: $url,
        );

        return new Payment(order: $order, checkout: $checkout);
    }
}
