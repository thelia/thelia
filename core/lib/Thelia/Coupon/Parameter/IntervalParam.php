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
 * Represent an DateTime period
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class IntervalParam implements ComparableInterface, RuleParameterInterface
{
    /** @var \DatePeriod Date period  */
    protected $datePeriod = null;

    /**
     * Constructor
     *
     * @param \DateTime     $start    Start interval
     * @param \DateInterval $interval Period
     */
    public function __construct(\DateTime $start, \DateInterval $interval)
    {
        $this->datePeriod = new \DatePeriod($start, $interval, 1);
    }

    /**
     * Get DatePeriod
     *
     * @return \DatePeriod
     */
    public function getDatePeriod()
    {
        return clone $this->datePeriod;
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
            throw new \InvalidArgumentException('IntervalParam can compare only DateTime');
        }

        /** @var \DateTime Start Date */
        $startDate = null;
        /** @var \DateTime End Date */
        $endDate = null;

        foreach ($this->datePeriod as $key => $value) {
            if ($key == 0) {
                $startDate = $value;
            }
            if ($key == 1) {
                $endDate = $value;
            }
        }

        $ret = -1;
        if ($startDate <= $other && $other <= $endDate) {
            $ret = 0;
        } elseif ($startDate > $other) {
            $ret = 1;
        } else {
            $ret = -1;
        }

        return $ret;
    }

    /**
     * Get Parameter value to test against
     *
     * @return \DatePeriod
     */
    public function getValue()
    {
        return clone $this->datePeriod;
    }
}