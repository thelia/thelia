<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Condition;

/**
 * A condition ready to be serialized and stored in DataBase
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class SerializableCondition
{
    /** @var string Condition Service id  */
    public $conditionServiceId = null;

    /** @var array Operators set by Admin for this Condition */
    public $operators = [];

    /** @var array Values set by Admin for this Condition */
    public $values = [];
}
