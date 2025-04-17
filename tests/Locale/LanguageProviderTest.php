<?php declare(strict_types=1);

namespace Nexi\Checkout\Tests\Locale;

use Nexi\Checkout\Locale\LanguageProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

class LanguageProviderTest extends TestCase
{
    #[DataProvider('languageProvider')]
    public function testGetLanguageReturnsMappedLanguageCode(string $shopwareLocaleCode, string $nexiLanguage): void
    {
        $languageEntity = $this->createLanguageEntity($shopwareLocaleCode);
        $context = Context::createDefaultContext();
        $languageRepository = $this->createMock(EntityRepository::class);
        $languageRepository->method('search')->willReturn(
            new EntitySearchResult(
                LanguageEntity::class,
                1,
                new EntityCollection([$languageEntity]),
                null,
                new Criteria(),
                $context
            )
        );

        $languageProvider = new LanguageProvider($languageRepository);

        $this->assertSame($nexiLanguage, $languageProvider->getLanguage($context));
    }

    public function testGetLanguageReturnsDefaultWhenLanguageNotFound(): void
    {
        $context = Context::createDefaultContext();
        $languageRepository = $this->createMock(EntityRepository::class);
        $languageRepository->method('search')->willReturn(
            new EntitySearchResult(
                LanguageEntity::class,
                0,
                new EntityCollection([]),
                null,
                new Criteria(),
                $context
            )
        );

        $languageProvider = new LanguageProvider($languageRepository);

        $this->assertSame('en-GB', $languageProvider->getLanguage($context));
    }

    public function testGetLanguageReturnsDefaultForUnmappedLanguageCode(): void
    {
        $languageEntity = $this->createLanguageEntity('fa-AF');
        $context = Context::createDefaultContext();
        $languageRepository = $this->createMock(EntityRepository::class);
        $languageRepository->method('search')->willReturn(
            new EntitySearchResult(
                LanguageEntity::class,
                1,
                new EntityCollection([$languageEntity]),
                null,
                new Criteria(),
                $context
            )
        );

        $languageProvider = new LanguageProvider($languageRepository);

        $this->assertSame('en-GB', $languageProvider->getLanguage($context));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function languageProvider(): iterable
    {
        yield ['de-DE', 'de-DE'];
        yield ['de-AT', 'de-DE'];
        yield ['nb-NO', 'nb-NO'];
        yield ['nn-NO', 'nb-NO'];
    }

    private function createLanguageEntity(string $localeCode): LanguageEntity
    {
        $localeEntity = new LocaleEntity();
        $localeEntity->setId('test-locale-id');
        $localeEntity->setCode($localeCode);

        $languageEntity = new LanguageEntity();
        $languageEntity->setId('test-language-id');
        $languageEntity->setLocale($localeEntity);

        return $languageEntity;
    }
}
