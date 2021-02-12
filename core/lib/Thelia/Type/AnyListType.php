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
 * Class AnyListType.
 *
 * @author GIlles Bourgeat <gbourgeat@openstudio.fr>
 */
class AnyListType extends BaseType
{
    public function getType()
    {
        return 'Any list type';
    }

    public function isValid($values)
    {
        return false === empty($values);
    }

    public function getFormattedValue($values)
    {
        return $this->isValid($values) ? explode(',', $values) : null;
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
