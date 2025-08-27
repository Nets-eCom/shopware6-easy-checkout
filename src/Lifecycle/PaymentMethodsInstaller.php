<?php

declare(strict_types=1);

namespace Nexi\Checkout\Lifecycle;

use Nexi\Checkout\Handler\EmbeddedPayment;
use Nexi\Checkout\Handler\HostedPayment;
use Nexi\Checkout\NexiCheckout;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class PaymentMethodsInstaller implements PaymentMethodInstallerInterface
{
    public const NEXI_CHECKOUT_HOSTED_TECHNICAL_NAME = 'nexi_checkout_hosted';

    public const NEXI_CHECKOUT_EMBEDDED_TECHNICAL_NAME = 'nexi_checkout_embedded';

    /**
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private PluginIdProvider $pluginIdProvider,
        private EntityRepository $paymentMethodRepository
    ) {
    }

    public function install(Context $context): void
    {
        $paymentMethods = [
            [
                'id' => $this->getMethodId($context, HostedPayment::class),
                'handlerIdentifier' => HostedPayment::class,
                'name' => 'Nexi Checkout Hosted',
                'description' => 'Nexi Checkout Hosted',
                'technicalName' => self::NEXI_CHECKOUT_HOSTED_TECHNICAL_NAME,
                'afterOrderEnabled' => true,
                'pluginId' => $this->pluginIdProvider->getPluginIdByBaseClass(NexiCheckout::class, $context),
            ],
            [
                'id' => $this->getMethodId($context, EmbeddedPayment::class),
                'handlerIdentifier' => EmbeddedPayment::class,
                'name' => 'Nexi Checkout Embedded',
                'description' => 'Nexi Checkout Embedded',
                'technicalName' => self::NEXI_CHECKOUT_EMBEDDED_TECHNICAL_NAME,
                'pluginId' => $this->pluginIdProvider->getPluginIdByBaseClass(NexiCheckout::class, $context),
            ],
        ];

        $this->paymentMethodRepository->upsert($paymentMethods, $context);
    }

    private function getMethodId(Context $context, string $handlerIdentifier): string
    {
        $previousMethodId = $this->getMethodIdSearchResult($context, $handlerIdentifier)->firstId();

        if ($previousMethodId !== null) {
            return $previousMethodId;
        }

        return Uuid::randomHex();
    }

    private function getMethodIdSearchResult(Context $context, string $handlerIdentifier): IdSearchResult
    {
        return $this
            ->paymentMethodRepository
            ->searchIds(
                (new Criteria())
                    ->addFilter(
                        new EqualsFilter('handlerIdentifier', $handlerIdentifier)
                    ),
                $context
            );
    }
}
