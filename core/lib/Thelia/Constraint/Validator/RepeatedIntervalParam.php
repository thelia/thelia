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

namespace Thelia\Constraint\Validator;

use Thelia\Coupon\CouponAdapterInterface;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Represent A repeated DateInterval across the time
 * Ex :
 * A duration of 1 month repeated every 2 months 5 times
 * ---------****----****----****----****----****----****-----------------> time
 *          1       2       3       4       5       6
 * 1        : $this->from           Start date of the repetition
 * ****---- : $this->interval       Duration of a whole cycle
 * x5       : $this->recurrences    How many repeated cycle, 1st excluded
 * x6       :                       How many occurrence
 * ****     : $this->durationInDays Duration of a period
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RepeatedIntervalParam extends RepeatedParam
{

    /** @var int duration of the param */
    protected $durationInDays = 1;

    /**
     * Get how many day a Param is lasting
     *
     * @return int
     */
    public function getDurationInDays()
    {
        return $this->durationInDays;
    }

    /**
     * Set how many day a Param is lasting
     *
     * @param int $durationInDays How many day a Param is lasting
     *
     * @return $this
     */
    public function setDurationInDays($durationInDays = 1)
    {
        $this->durationInDays = $durationInDays;

        return $this;
    }

    /**
     * Constructor
     *
     * @param CouponAdapterInterface $adapter Provide necessary value from Thelia
     */
    public function __construct(CouponAdapterInterface $adapter)
    {
        $this->defaultConstructor();
        $this->adapter = $adapter;
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
            throw new \InvalidArgumentException('RepeatedIntervalParam can compare only DateTime');
        }

        $ret = -1;
        $dates = array();
        /** @var $value \DateTime */
        foreach ($this->datePeriod as $value) {
            $dates[$value->getTimestamp()]['startDate'] = $value;
            $endDate = new \DateTime();
            $dates[$value->getTimestamp()]['endDate'] = $endDate->setTimestamp(
                $value->getTimestamp() + ($this->durationInDays * 60 *60 *24)
            );
        }

        foreach ($dates as $date) {
            if ($date['startDate'] <= $other && $other <= $date['endDate']) {
                return 0;
            }
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

    /**
     * Get I18n tooltip
     *
     * @return string
     */
    public function getToolTip()
    {
        return $this->adapter
            ->getTranslator()
            ->trans('A date (ex: YYYY-MM-DD HH:MM:SS)', null, 'constraint');
    }
}