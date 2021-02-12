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
class EnumListType extends BaseType
{
    protected $values = [];

    public function __construct($values = [])
    {
        if (\is_array($values)) {
            $this->values = $values;
        }
    }

    public function addValue($value): void
    {
        if (!\in_array($value, $this->values)) {
            $this->values[] = $value;
        }
    }

    /**
     * @param array|\Traversable $values
     *
     * @since 2.3.0
     */
    public function addValues($values): void
    {
        if (!\is_array($values) && !$values instanceof \Traversable) {
            throw new \InvalidArgumentException('$values must be an array or an instance of \Traversable');
        }

        foreach ($values as $value) {
            $this->addValue($value);
        }
    }

    public function getType()
    {
        return 'Enum list type';
    }

    public function isValid($values)
    {
        foreach (explode(',', $values) as $value) {
            if (!$this->isSingleValueValid($value)) {
                return false;
            }
        }

        return true;
    }

    public function getFormattedValue($values)
    {
        return $this->isValid($values) ? explode(',', $values) : null;
    }

    public function isSingleValueValid($value)
    {
        return \in_array($value, $this->values);
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
