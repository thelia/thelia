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
class FloatType extends BaseType
{
    public function getType(): string
    {
        return 'Float type';
    }

    public function isValid($value): bool
    {
        return filter_var($value, \FILTER_VALIDATE_FLOAT) !== false;
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
