<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Set\ValueObject\SetList;

/** @phpstan-ignore-next-line  */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->phpVersion(PhpVersion::PHP_74); /** @phpstan-ignore-line  */
    $rectorConfig->paths([
        __DIR__ . '/src',
    ]);

    $rectorConfig->skip([
        __DIR__ . '/src/Migration',
    ]);

    $rectorConfig->sets([
        SetList::CODE_QUALITY, /** @phpstan-ignore-line  */
        SetList::CODING_STYLE, /** @phpstan-ignore-line  */
        SetList::DEAD_CODE, /** @phpstan-ignore-line  */
        SetList::STRICT_BOOLEANS, /** @phpstan-ignore-line  */
    ]);

    $rectorConfig->importNames();
};
