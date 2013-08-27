<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Coupon\Validator;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Represent a Quantity
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class QuantityParam extends IntegerParam
{

    /**
     * Constructor
     *
     * @param int $integer Integer
     */
    public function __construct($integer)
    {
        if ($integer < 0) {
            $integer = 0;
        }
        $this->integer = $integer;
    }

    /**
     * Compare the current object to the passed $other.
     *
     * Returns 0 if they are semantically equal, 1 if the other object
     * is less than the current one, or -1 if its more than the current one.
     *
     * This method should not check for identity using ===, only for semantically equality for example
     * when two different DateTime instances point to the exact same Date + TZ.
     *
     * @param mixed $other Object
     *
     * @throws \InvalidArgumentException
     * @return int
     */
    public function compareTo($other)
    {
        if (!is_integer($other) || $other < 0) {
            throw new \InvalidArgumentException(
                'IntegerParam can compare only positive int'
            );
        }

        return parent::compareTo($other);
    }

}
