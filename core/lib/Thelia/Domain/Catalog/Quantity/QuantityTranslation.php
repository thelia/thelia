<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Domain\Catalog\Quantity;

use Thelia\Core\Translation\Translator;

/**
 * The messages of the quantity rules, in the core catalog. Without a booted translator
 * (a unit test, a script) the English text is used.
 *
 * @internal
 */
final class QuantityTranslation
{
    /**
     * @param array<string, string> $parameters
     */
    public static function trans(string $message, array $parameters = []): string
    {
        try {
            return Translator::getInstance()->trans($message, $parameters);
        } catch (\RuntimeException) {
            return strtr($message, $parameters);
        }
    }

    public static function locale(): string
    {
        try {
            return Translator::getInstance()->getLocale();
        } catch (\RuntimeException) {
            return 'en_US';
        }
    }
}
