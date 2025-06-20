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
namespace Thelia\Type;

/**
 * This filter accepts either a boolean value, or '*' which means both, true and false.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class BooleanOrBothType extends BaseType
{
    public const ANY = '*';

    public function getType(): string
    {
        return 'Boolean or both type';
    }

    public function isValid($value): bool
    {
        return $value === self::ANY || filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) !== null;
    }

    public function getFormattedValue($value)
    {
        if ($value === self::ANY) {
            return $value;
        }

        return $value === null ? null : filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
    }

    public function getFormOptions(): array
    {
        return [];
    }
}
