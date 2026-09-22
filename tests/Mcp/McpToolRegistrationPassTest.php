<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Mcp;

use Nexi\Checkout\DependencyInjection\McpToolRegistrationPass;
use Nexi\Checkout\Mcp\Tool\AbstractTool;
use Nexi\Checkout\Mcp\Tool\CancelPaymentTool;
use Nexi\Checkout\Mcp\Tool\ChargePaymentTool;
use Nexi\Checkout\Mcp\Tool\PaymentDetailsTool;
use Nexi\Checkout\Mcp\Tool\RefundPaymentTool;
use Nexi\Checkout\NetsNexiCheckout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolDiscoveryCompilerPass;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Symfony\AI\McpBundle\DependencyInjection\McpPass;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @TODO remove after Shopware upstream fix
 */
final class McpToolRegistrationPassTest extends TestCase
{
    private const TOOLS = [PaymentDetailsTool::class, ChargePaymentTool::class, RefundPaymentTool::class, CancelPaymentTool::class];

    public function testPluginToolsReachLocatorBeforeShopwareRemapping(): void
    {
        $container = $this->buildContainer([]);
        $services = $this->getLocatorServices($container);

        foreach (self::TOOLS as $tool) {
            $this->assertArrayHasKey($tool, $services);
            $this->assertCount(1, $container->getDefinition($tool)->getTag('mcp.tool'));
        }
    }

    public function testExcludedToolsDoNotLeaveDanglingLocatorReferences(): void
    {
        $container = $this->buildContainer(['shopware-entity-search', 'nexi-checkout-payment-details']);
        $services = $this->getLocatorServices($container);

        $this->assertArrayHasKey(PaymentDetailsTool::class, $services);
        foreach ([ChargePaymentTool::class, RefundPaymentTool::class, CancelPaymentTool::class] as $tool) {
            $this->assertArrayNotHasKey($tool, $services);
            $this->assertFalse($container->hasDefinition($tool));
        }
    }

    public function testPassIgnoresOtherPluginsAndAbsentServer(): void
    {
        $container = new ContainerBuilder();
        $own = $container->register(ChargePaymentTool::class)->addTag('shopware.mcp.tool');
        $other = $container->register('OtherPlugin\\Tool')->addTag('shopware.mcp.tool');
        $pass = new McpToolRegistrationPass();
        $pass->process($container);
        $this->assertFalse($own->hasTag('mcp.tool'));

        $container->register('mcp.server.builder', \stdClass::class);
        $pass->process($container);
        $pass->process($container);
        $this->assertCount(1, $own->getTag('mcp.tool'));
        $this->assertFalse($other->hasTag('mcp.tool'));
    }

    #[DataProvider('invalidAllowedToolsProvider')]
    public function testPassRejectsNonArrayAllowedTools(mixed $allowedTools): void
    {
        $container = new ContainerBuilder();
        $container->register('mcp.server.builder', \stdClass::class);
        $container->setParameter('shopware.mcp.allowed_tools', $allowedTools);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "shopware.mcp.allowed_tools" parameter must be an array.');

        (new McpToolRegistrationPass())->process($container);
    }

    /**
     * @return iterable<string, array{string|bool|int|float|null}>
     */
    public static function invalidAllowedToolsProvider(): iterable
    {
        yield 'string' => ['nexi-checkout-payment-details'];
        yield 'boolean' => [false];
        yield 'integer' => [1];
        yield 'float' => [1.5];
        yield 'null' => [null];
    }

    public function testPassSkipsMissingClassesAndAbstractDefinitionsAndClasses(): void
    {
        require_once __DIR__ . '/Fixtures/AbstractTool.php';

        foreach ([[], ['nexi-checkout-payment-details']] as $allowedTools) {
            $container = new ContainerBuilder();
            $container->register('mcp.server.builder', \stdClass::class);
            $container->setParameter('shopware.mcp.allowed_tools', $allowedTools);
            $missing = $container->register('Nexi\\Checkout\\Mcp\\Tool\\MissingTool')->addTag('shopware.mcp.tool');
            $abstractDefinition = $container->register('abstract.tool', PaymentDetailsTool::class)
                ->setAbstract(true)
                ->addTag('shopware.mcp.tool');
            $abstractClass = $container->register(AbstractTool::class)
                ->addTag('shopware.mcp.tool');
            $concrete = $container->register(PaymentDetailsTool::class)->addTag('shopware.mcp.tool');

            (new McpToolRegistrationPass())->process($container);

            $this->assertFalse($missing->hasTag('mcp.tool'));
            $this->assertFalse($abstractDefinition->hasTag('mcp.tool'));
            $this->assertFalse($abstractClass->hasTag('mcp.tool'));
            $this->assertTrue($concrete->hasTag('mcp.tool'));
        }
    }

    /**
     * @param list<string> $allowedTools
     */
    private function buildContainer(array $allowedTools): ContainerBuilder
    {
        if (!class_exists(McpPass::class) || !class_exists(McpToolDiscoveryCompilerPass::class)) {
            $this->markTestSkipped('Requires the Shopware MCP integration.');
        }

        $container = new ContainerBuilder();
        $container->register('mcp.server.builder', \stdClass::class);
        $container->register(EntitySearchTool::class)->addTag('mcp.tool');
        $container->setParameter('shopware.mcp.allowed_tools', $allowedTools);
        // Reproduce Symfony being registered before Shopware's discovery pass.
        $container->addCompilerPass(new McpPass());
        $container->addCompilerPass(new McpToolDiscoveryCompilerPass());

        $plugin = new NetsNexiCheckout(true, \dirname(__DIR__, 2));
        (new \ReflectionMethod($plugin, 'registerMcpServices'))->invoke($plugin, $container);
        foreach (self::TOOLS as $tool) {
            $this->assertFalse($container->getDefinition($tool)->hasTag('mcp.tool'));
        }

        foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
            if ($pass instanceof McpPass || $pass instanceof McpToolDiscoveryCompilerPass || $pass instanceof McpToolRegistrationPass) {
                $pass->process($container);
            }
        }

        return $container;
    }

    /**
     * @return array<string, ServiceClosureArgument>
     */
    private function getLocatorServices(ContainerBuilder $container): array
    {
        foreach ($container->getDefinition('mcp.server.builder')->getMethodCalls() as [$method, $arguments]) {
            if ($method === 'setContainer') {
                return $container->getDefinition((string) $arguments[0])->getArgument(0);
            }
        }

        $this->fail('MCP service locator was not configured.');
    }
}
