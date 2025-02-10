<?php

declare(strict_types=1);

namespace Nexi\Checkout;

use Doctrine\DBAL\Connection;
use Nexi\Checkout\Lifecycle\HostedPaymentMethodActivator;
use Nexi\Checkout\Lifecycle\HostedPaymentMethodInstaller;
use Nexi\Checkout\Lifecycle\UserDataRemover;
use Nexi\Checkout\Lifecycle\UserDataRemoverInterface;
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
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class NexiCheckout extends Plugin
{
    public const COMMERCE_PLATFORM_TAG = 'Shopware6';

    public const PLUGIN_VERSION = '2.0.0';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->registerPackagesConfigFile($container);
    }

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $this
            ->getPaymentMethodInstalled()
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

    private function getPaymentMethodInstalled(): HostedPaymentMethodInstaller
    {
        return new HostedPaymentMethodInstaller(
            $this->container->get(PluginIdProvider::class),
            $this->container->get(\sprintf('%s.repository', PaymentMethodDefinition::ENTITY_NAME))
        );
    }

    private function getPaymentMethodActivator(): HostedPaymentMethodActivator
    {
        return new HostedPaymentMethodActivator(
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
}
