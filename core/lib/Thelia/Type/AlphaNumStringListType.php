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
class AlphaNumStringListType extends BaseType
{
    public function getType()
    {
        return 'Alphanumeric string list type';
    }

    public function isValid($values)
    {
        if (null === $values) {
            return false;
        }
        foreach (explode(',', $values) as $value) {
            if (!preg_match('#^[a-zA-Z0-9\-_\.]+$#', $value)) {
                return false;
            }
        }

        return true;
    }

    public function getFormattedValue($values)
    {
        return $this->isValid($values) ? explode(',', $values) : null;
    }

    public function getFormOptions()
    {
        return [];
    }
}
