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
 * The quantity rules given to a combination could not decide any quantity: a step or a
 * minimum that is not positive, more decimals than a quantity may carry, or a step the
 * decimals cannot write.
 */
final class InvalidQuantityRuleException extends \InvalidArgumentException
{
    public static function notPositive(string $field): self
    {
        return new self(self::trans('The %field of a combination must be greater than zero.', ['%field' => self::trans($field)]));
    }

    public static function decimalsOutOfRange(int $decimals): self
    {
        return new self(self::trans(
            'A quantity may carry between 0 and %max decimals, %decimals were asked for.',
            ['%max' => (string) QuantityScale::MAXIMUM_DECIMALS, '%decimals' => (string) $decimals],
        ));
    }

    public static function moreDecimalsThanAllowed(string $field, int $decimals): self
    {
        return new self(self::trans(
            'The %field of a combination cannot carry more decimals than its quantities (%decimals).',
            ['%field' => self::trans($field), '%decimals' => (string) $decimals],
        ));
    }

    /**
     * @param array<string, string> $parameters
     */
    private static function trans(string $message, array $parameters = []): string
    {
        try {
            return Translator::getInstance()->trans($message, $parameters);
        } catch (\RuntimeException) {
            return strtr($message, $parameters);
        }
    }
}
