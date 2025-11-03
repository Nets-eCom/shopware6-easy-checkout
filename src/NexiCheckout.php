<?php

declare(strict_types=1);

namespace Nexi\Checkout;

use Doctrine\DBAL\Connection;
use Nexi\Checkout\Lifecycle\PaymentMethodInstallerInterface;
use Nexi\Checkout\Lifecycle\PaymentMethodsActivator;
use Nexi\Checkout\Lifecycle\PaymentMethodsInstaller;
use Nexi\Checkout\Lifecycle\UserDataRemover;
use Nexi\Checkout\Lifecycle\UserDataRemoverInterface;
use Nexi\Checkout\Subscriber\EmbeddedCreatePaymentOnCheckoutSubscriber;
use Nexi\Checkout\Subscriber\SendProvisionReportOnOrderPlacedSubscriber;
use NexiCheckout\Factory\Provider\HttpClientConfigurationProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class NexiCheckout extends Plugin
{
    public const COMMERCE_PLATFORM_TAG = 'Shopware6';

    public const PLUGIN_VERSION = '2.0.4';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->registerPackagesConfigFile($container);
        $this->registerApiUrlParameters($container);
        $this->registerProvisionSubscriber($container);
    }

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $this
            ->getPaymentMethodInstaller()
            ->install($installContext->getContext());
    }

    /**
     * {@inheritDoc}
     */
    public function executeComposerCommands(): bool
    {
        return true;
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        $this->getPaymentMethodActivator()->deactivate($uninstallContext->getContext());

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->getUserDataRemover()->removeUserData($this->container->get(Connection::class));
    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);
        $this->getPaymentMethodActivator()->activate($activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);
        $this->getPaymentMethodActivator()->deactivate($deactivateContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        // Update necessary stuff, mostly non-database related
    }

    public function postInstall(InstallContext $installContext): void
    {
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
    }

    private function getPaymentMethodInstaller(): PaymentMethodInstallerInterface
    {
        return new PaymentMethodsInstaller(
            $this->container->get(PluginIdProvider::class),
            $this->container->get(\sprintf('%s.repository', PaymentMethodDefinition::ENTITY_NAME))
        );
    }

    private function getPaymentMethodActivator(): PaymentMethodsActivator
    {
        return new PaymentMethodsActivator(
            $this->container->get(\sprintf('%s.repository', PaymentMethodDefinition::ENTITY_NAME)),
            $this->container->get(SystemConfigService::class)
        );
    }

    private function getUserDataRemover(): UserDataRemoverInterface
    {
        return new UserDataRemover();
    }

    private function registerPackagesConfigFile(ContainerBuilder $container): void
    {
        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = rtrim($this->getPath(), '/') . '/Resources/config';

        $configLoader->load($confDir . '/{packages}/*.yaml', 'glob');
    }

    private function registerApiUrlParameters(ContainerBuilder $container): void
    {
        // TODO: get values from SDK
        $container->setParameter('env(NEXI_CHECKOUT_API_LIVE_URL)', 'https://api.dibspayment.eu');
        $container->setParameter('env(NEXI_CHECKOUT_API_TEST_URL)', 'https://test.api.dibspayment.eu');
        $container->setParameter(
            'env(NEXI_CHECKOUT_JS_LIVE_URL)',
            'https://checkout.dibspayment.eu/v1/checkout.js?v=1'
        );
        $container->setParameter(
            'env(NEXI_CHECKOUT_JS_TEST_URL)',
            'https://test.checkout.dibspayment.eu/v1/checkout.js?v=1'
        );

        $container
            ->getDefinition(HttpClientConfigurationProvider::class)
            ->setArguments(
                [
                    '%env(NEXI_CHECKOUT_API_LIVE_URL)%',
                    '%env(NEXI_CHECKOUT_API_TEST_URL)%',
                ]
            );

        $subscriberDefinition = $container->getDefinition(EmbeddedCreatePaymentOnCheckoutSubscriber::class);
        $subscriberDefinition->setArgument('$liveCheckoutJsUrl', '%env(NEXI_CHECKOUT_JS_LIVE_URL)%');
        $subscriberDefinition->setArgument('$testCheckoutJsUrl', '%env(NEXI_CHECKOUT_JS_TEST_URL)%');
    }

    private function registerProvisionSubscriber(ContainerBuilder $container, string $identifier = ''): void
    {
        if ($identifier === '') {
            return;
        }

        $definition = (new Definition(
            SendProvisionReportOnOrderPlacedSubscriber::class,
            [
                new Reference(ClientInterface::class),
                new Reference(RequestFactoryInterface::class),
                new Reference(StreamFactoryInterface::class),
                new Reference('monolog.logger.nexicheckout_channel'),
                '%instance_id%',
                '%kernel.shopware_version%',
                $identifier,
            ]
        ))
            ->setPublic(false)
            ->setTags(
                [
                    'kernel.event_subscriber' => [],
                ]
            );

        $container->setDefinition(
            SendProvisionReportOnOrderPlacedSubscriber::class,
            $definition
        );
    }
}
