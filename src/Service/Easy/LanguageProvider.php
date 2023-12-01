<?php

declare(strict_types=1);

namespace Nets\Checkout\Service\Easy;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class LanguageProvider
{
    private const DEFALUT_LANGUAGE = 'en-GB';

    private EntityRepository $languageRepository;

    public function __construct(EntityRepository $languageRepository) {
        $this->languageRepository = $languageRepository;
    }

    public function getLanguage(Context $context): string
    {
        $languageId = $context->getLanguageId();
        $criteria = new Criteria([$languageId]);
        $criteria->addAssociation('locale');

        /** @var null|LanguageEntity $language */
        $language = $this->languageRepository->search($criteria, $context)->first();

        if ($language === null || $language->getLocale() === null) {
            return self::DEFALUT_LANGUAGE;
        }

        // make sure that all de-.. codes will go to de-DE etc.
        $langShort = substr($language->getLocale()->getCode(), 0, 2);
        switch ($langShort) {
            case 'de':
                return 'de-DE';
            case 'da':
                return 'da-DK';
            case 'sv':
                return 'sv-SE';
            case 'nb':
                return 'nb-NO';
            default:
                return self::DEFALUT_LANGUAGE;
        }
    }
}
