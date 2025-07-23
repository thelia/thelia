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
class FloatToFloatArrayType extends BaseType
{
    public function getType(): string
    {
        return 'Float key to float value array type';
    }

    public function isValid($value): bool
    {
        if (!\is_array($value)) {
            return false;
        }

        foreach ($value as $key => $val) {
            if (false === filter_var($key, \FILTER_VALIDATE_FLOAT) || false === filter_var($val, \FILTER_VALIDATE_FLOAT)) {
                return false;
            }
        }

        return true;
    }

    public function getFormattedValue($value)
    {
        return $this->isValid($value) ? $value : null;
    }

    public function getFormOptions(): array
    {
        return [];
    }
}
