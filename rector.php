<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\String_\SymplifyQuoteEscapeRector;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        strictBooleans: true
    )
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
    ])
    ->withAttributesSets(
        symfony: true,
        doctrine: true
    )
    ->withSkip([
        SymplifyQuoteEscapeRector::class,
    ])
    ->withImportNames(importShortClasses: false);
