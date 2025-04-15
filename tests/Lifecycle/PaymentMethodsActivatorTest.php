<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Lifecycle;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Handler\EmbeddedPayment;
use Nexi\Checkout\Handler\HostedPayment;
use Nexi\Checkout\Lifecycle\PaymentMethodsActivator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class PaymentMethodsActivatorTest extends TestCase
{
    #[DataProvider('activateProvider')]
    public function testActivate(InvokedCount $invokedCount, string $hostedId, string $embeddedId, bool $active): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->createPaymentMethodRepository(
            $context,
            $this->createSearchResult(
                $context,
                $hostedId === '' ? [] : [
                    [
                        'primaryKey' => $hostedId,
                        'data' => [],
                    ],
                ]
            ),
            $this->createSearchResult(
                $context,
                $embeddedId === '' ? [] : [
                    [
                        'primaryKey' => $embeddedId,
                        'data' => [],
                    ],
                ]
            )
        );

        $repository
            ->expects($invokedCount)
            ->method('update')
            ->with(
                [
                    [
                        'id' => $hostedId,
                        'active' => $active,
                    ],
                    [
                        'id' => $embeddedId,
                        'active' => $active,
                    ],
                ],
                $context
            );

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->method('getString')
            ->with(ConfigurationProvider::WEBHOOK_AUTHORIZATION_HEADER)
            ->willReturn('');
        $systemConfigService
            ->expects(clone $invokedCount)
            ->method('set')
            ->with(
                ConfigurationProvider::WEBHOOK_AUTHORIZATION_HEADER,
                $this->isString()
            );

        $sut = new PaymentMethodsActivator(
            $repository,
            $systemConfigService
        );

        $sut->activate($context);
    }

    #[DataProvider('activateProvider')]
    public function testDeactivate(InvokedCount $invokedCount, string $hostedId, string $embeddedId, bool $active): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->createPaymentMethodRepository(
            $context,
            $this->createSearchResult(
                $context,
                $hostedId === '' ? [] : [
                    [
                        'primaryKey' => $hostedId,
                        'data' => [],
                    ],
                ]
            ),
            $this->createSearchResult(
                $context,
                $embeddedId === '' ? [] : [
                    [
                        'primaryKey' => $embeddedId,
                        'data' => [],
                    ],
                ]
            )
        );

        $repository
            ->expects($invokedCount)
            ->method('update')
            ->with(
                [
                    [
                        'id' => $hostedId,
                        'active' => !$active,
                    ],
                    [
                        'id' => $embeddedId,
                        'active' => !$active,
                    ],
                ],
                $context
            );


        $sut = new PaymentMethodsActivator(
            $repository,
            $this->createMock(SystemConfigService::class)
        );
        $sut->deactivate($context);
    }

    /**
     * @return iterable<array{InvokedCount, string, string, bool}>
     */
    public static function activateProvider(): iterable
    {
        yield [new InvokedCount(0), '', '', true];
        yield [new InvokedCount(1), '1234', '1235', true];
    }

    /**
     * @return MockObject|EntityRepository<PaymentMethodCollection>
     */
    private function createPaymentMethodRepository(
        Context $context,
        IdSearchResult $idSearchResultHosted,
        IdSearchResult $idSearchResultEmbedded
    ): MockObject|EntityRepository {
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->method('searchIds')
            ->willReturnCallback(
                function (Criteria $criteria, Context $passedContext) use ($idSearchResultHosted, $idSearchResultEmbedded, $context) {
                    if ($context !== $passedContext) {
                        return null;
                    }

                    $criteriaBoth = (new Criteria())
                        ->addFilter(
                            new EqualsAnyFilter('handlerIdentifier', [HostedPayment::class, EmbeddedPayment::class])
                        );
                    if ((string) $criteriaBoth === (string) $criteria) {
                        return IdSearchResult::fromIds(
                            array_merge($idSearchResultHosted->getIds(), $idSearchResultEmbedded->getIds()),
                            $criteria,
                            $context
                        );
                    }

                    $criteriaHosted = (new Criteria())
                        ->addFilter(
                            new EqualsAnyFilter('handlerIdentifier', [HostedPayment::class])
                        );
                    if ((string) $criteriaHosted === (string) $criteria) {
                        return $idSearchResultHosted;
                    }

                    $criteriaEmbedded = (new Criteria())
                        ->addFilter(
                            new EqualsAnyFilter('handlerIdentifier', [EmbeddedPayment::class])
                        );
                    if ((string) $criteriaEmbedded === (string) $criteria) {
                        return $idSearchResultEmbedded;
                    }

                    return null;
                }
            );

        return $repository;
    }

    /**
     * @param array<array<string, mixed>> $data
     */
    private function createSearchResult(Context $context, array $data): IdSearchResult
    {
        return new IdSearchResult(
            \count($data),
            $data,
            new Criteria(),
            $context
        );
    }
}
