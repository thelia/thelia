<?php

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
class EnumType extends BaseType
{
    protected $values = [];

    public function __construct($values = [])
    {
        if (\is_array($values)) {
            $this->values = $values;
        }
    }

    public function getType()
    {
        return 'Enum type';
    }

    public function isValid($value)
    {
        return \in_array($value, $this->values);
    }

    public function getFormattedValue($value)
    {
        return $this->isValid($value) ? $value : null;
    }

    public function getFormType()
    {
        return 'text';
    }

    public function getFormOptions()
    {
        return [];
    }
}
