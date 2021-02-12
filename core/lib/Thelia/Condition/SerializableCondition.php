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

namespace Thelia\Condition;

/**
 * A condition ready to be serialized and stored in DataBase.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class SerializableCondition
{
    /** @var string Condition Service id */
    public $conditionServiceId;

    /** @var array Operators set by Admin for this Condition */
    public $operators = [];

    /** @var array Values set by Admin for this Condition */
    public $values = [];
}
