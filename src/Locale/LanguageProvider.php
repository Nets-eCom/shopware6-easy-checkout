<?php

declare(strict_types=1);

namespace Nexi\Checkout\Locale;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;

class LanguageProvider
{
    private const DEFALUT_LANGUAGE = 'en-GB';

    /**
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(private readonly EntityRepository $languageRepository)
    {
    }

    public function getLanguage(Context $context): string
    {
        $languageId = $context->getLanguageId();
        $criteria = new Criteria([$languageId]);
        $criteria->addAssociation('locale');

        /** @var LanguageEntity|null $language */
        $language = $this->languageRepository->search($criteria, $context)->first();

        if ($language === null || $language->getLocale() === null) {
            return self::DEFALUT_LANGUAGE;
        }

        // make sure that all de-.. codes will go to de-DE etc.
        $langShort = substr($language->getLocale()->getCode(), 0, 2);

        return match ($langShort) {
            'da' => 'da-DK',
            'nl' => 'nl-NL',
            'ee' => 'ee-EE',
            'fi' => 'fi-FI',
            'fr' => 'fr-FR',
            'de' => 'de-DE',
            'it' => 'it-IT',
            'lv' => 'lv-LV',
            'lt' => 'lt-LT',
            'nb' => 'nb-NO',
            'nn' => 'nb-NO',
            'pl' => 'pl-PL',
            'es' => 'es-ES',
            'sk' => 'sk-SK',
            'sv' => 'sv-SE',
            default => self::DEFALUT_LANGUAGE,
        };
    }
}
