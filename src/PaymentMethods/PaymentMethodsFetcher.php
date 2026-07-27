<?php

declare(strict_types=1);

namespace Nexi\Checkout\PaymentMethods;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Request\PaymentMethods;
use Psr\Log\LoggerInterface;

class PaymentMethodsFetcher implements PaymentMethodsFetcherInterface
{
    private const CARD_PAYMENT_TYPE = 'Card';

    public function __construct(
        private readonly PaymentApiFactory $paymentApiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<string>
     */
    public function fetchAvailableMethodNames(?string $salesChannelId, ?string $currencyIsoCode = null): array
    {
        $secretKey = $this->configurationProvider->getSecretKey($salesChannelId);

        if ($secretKey === '') {
            return [self::CARD_PAYMENT_TYPE];
        }

        try {
            $paymentApi = $this->paymentApiFactory->create(
                $secretKey,
                $this->configurationProvider->isLiveMode($salesChannelId)
            );
            $result = $paymentApi->getPaymentMethods(new PaymentMethods($currencyIsoCode, true));
        } catch (PaymentApiException $e) {
            $this->logger->error('Failed to fetch payment methods from Nexi API', [
                'message' => $e->getMessage(),
            ]);

            return [self::CARD_PAYMENT_TYPE];
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error fetching payment methods', [
                'message' => $e->getMessage(),
            ]);

            return [self::CARD_PAYMENT_TYPE];
        }

        $names = [self::CARD_PAYMENT_TYPE];
        foreach ($result->getMethods() as $method) {
            if ($method->getPaymentType() === self::CARD_PAYMENT_TYPE) {
                continue;
            }

            $name = $method->getName();
            if ($name !== null && !\in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
