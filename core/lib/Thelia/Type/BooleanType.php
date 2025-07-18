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
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class BooleanType extends BaseType
{
    public function getType(): string
    {
        return 'Boolean type';
    }

    public function isValid($value): bool
    {
        return null !== filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
    }

    public function getFormattedValue($value)
    {
        return null === $value ? null : filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
    }

    public function getFormOptions(): array
    {
        return [];
    }
}
