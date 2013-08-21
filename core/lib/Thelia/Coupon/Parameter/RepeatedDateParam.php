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

namespace Thelia\Coupon\Parameter;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Represent A repeated Date across the time
 * Ex :
 * A date repeated every 1 months 5 times
 * ---------*---*---*---*---*---*---------------------------> time
 *          1   2   3   4   5   6
 * 1    : $this->from        Start date of the repetition
 * *--- : $this->interval    Duration of a whole cycle
 * x5   : $this->recurrences How many repeated cycle, 1st excluded
 * x6   :                    How many occurrence
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RepeatedDateParam extends RepeatedParam
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->defaultConstructor();
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
        if (!$other instanceof \DateTime) {
            throw new \InvalidArgumentException('RepeatedDateParam can compare only DateTime');
        }

        $ret = -1;
        $dates = array();
        /** @var $value \DateTime */
        foreach ($this->datePeriod as $value) {
            $dates[$value->getTimestamp()] = $value;
        }

        foreach ($dates as $date) {
            if ($date == $other) {
                return 0;
            }
        }

        return $ret;
    }
}