<?php

declare(strict_types=1);

namespace Nexi\Checkout\Subscriber;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SendProvisionReportOnOrderPlacedSubscriber implements EventSubscriberInterface
{
    private const SHOPWARE_PROVISION_URL = 'https://api.shopware.com/shopwarepartners/reports/technology';

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LoggerInterface $logger,
        private readonly string $instanceId,
        private readonly string $shopwareVersion,
        private readonly string $provisionIdentifier
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onCheckoutOrderPlaced',
        ];
    }

    public function onCheckoutOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        if ($this->provisionIdentifier === '') {
            return;
        }

        $request = $this->requestFactory->createRequest('POST', self::SHOPWARE_PROVISION_URL)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->streamFactory->createStream(
                    json_encode(
                        [
                            'identifier' => $this->provisionIdentifier,
                            'reportDate' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
                            'instanceId' => $this->instanceId,
                            'shopwareVersion' => $this->shopwareVersion,
                            'reportDataKeys' => [
                                'numberOfFulfilledOrders' => 1,
                            ],
                        ]
                    )
                )
            );

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $clientException) {
            $this->logError($clientException->getMessage());

            return;
        }

        if (!$this->isSuccessCode($response->getStatusCode())) {
            $this->logError($response->getBody()->getContents());
        }
    }

    private function isSuccessCode(int $code): bool
    {
        return $code >= 200 && $code < 300;
    }

    private function logError(string $message): void
    {
        $this->logger->error('Couldn\'t send provision report', [
            'message' => $message,
        ]);
    }
}
