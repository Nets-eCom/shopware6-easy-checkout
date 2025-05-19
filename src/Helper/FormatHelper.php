<?php

declare(strict_types=1);

namespace Nexi\Checkout\Helper;

class FormatHelper
{
    /**
     * regexp for filtering strings
     */
    private const ALLOWED_CHARACTERS_PATTERN = '/[^\x{00A1}-\x{00AC}\x{00AE}-\x{00FF}\x{0100}-\x{017F}\x{0180}-\x{024F}\x{0250}-\x{02AF}\x{02B0}-\x{02FF}\x{0300}-\x{036F}A-Za-z0-9\!\#\$\%\(\)*\+\,\-\.\/\:\;\\=\?\@\[\]\\^\_\`\{\}\~ ]+/u';

    public function sanitizeString(string $string): string
    {
        $string = substr($string, 0, 128);
        $name = preg_replace(self::ALLOWED_CHARACTERS_PATTERN, '', $string);

        if (empty($name)) {
            return preg_replace('/[^A-Za-z0-9() -]/', '', $string);
        }

        return $name;
    }

    public function priceToInt(float $price): int
    {
        return (int) round($price * 100);
    }

    public function priceToFloat(int $price): float
    {
        return (float) number_format($price / 100, 2, '.', '');
    }
}
