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

use DateInterval;
use DatePeriod;
use DateTime;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Allow to set the way a parameter can be repeated across the time
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
abstract class RepeatedParam extends RuleParameterAbstract
{
    /** @var DateTime The start date of the period. */
    protected $from = null;

    /** @var DateInterval The interval between recurrences within the period. */
    protected $interval = null;

    /** @var int Nb time the object will be repeated (1st occurrence excluded). */
    protected $recurrences = null;

    /** @var DatePeriod dates recurring at regular intervals, over a given period */
    protected $datePeriod = null;

    /** @var int Frequency the object will be repeated */
    protected $frequency = null;

    /** @var int $nbRepetition Time the object will be repeated  */
    protected $nbRepetition = null;

    /**
     * Get frequency
     *
     * @return int
     */
    public function getFrequency()
    {
        return $this->frequency;
    }

    /**
     * Get Interval
     *
     * @return \DateInterval
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * Get number of time it will be repeated
     *
     * @return int
     */
    public function getNbRepetition()
    {
        return $this->nbRepetition;
    }

    /**
     * Get number of recurrences
     *
     * @return int
     */
    public function getRecurrences()
    {
        return $this->recurrences;
    }

    /**
     * Generate default repetition
     * Every 1 week 100 times from now
     *
     * @return $this
     */
    protected function defaultConstructor()
    {
        $this->from = new \DateTime();
        $this->interval = new \DateInterval('P1W'); // 1 week
        $this->recurrences = 100;
        $this->generateDatePeriod();

        return $this;
    }

    /**
     * Generate DatePeriod from class attributes
     * Will repeat every DatePeriod
     *
     * @return $this
     */
    protected function generateDatePeriod()
    {
        $this->datePeriod = new DatePeriod(
            $this->from,
            $this->interval,
            $this->recurrences
        );

        return $this;
    }
    
    /**
     * Set the Object to be repeated every days
     * Ex : $obj->repeatEveryDay() will occur once
     *      $obj->repeatEveryDay(10) will occur once
     *      $obj->repeatEveryDay(10, 0) will occur once
     *      $obj->repeatEveryDay(10, 4) will occur every 10 days 5 times
     *
     * @param int $frequency    Frequency the object will be repeated
     * @param int $nbRepetition Time the object will be repeated
     *
     * @return $this
     */
    public function repeatEveryDay($frequency = 1, $nbRepetition = 0)
    {
        $this->_repeatEveryPeriod($period = 'D', $frequency, $nbRepetition);

        return $this;
    }

    /**
     * Set the Object to be repeated every week
     * Ex : $obj->repeatEveryWeek() will occur once
     *      $obj->repeatEveryWeek(10) will occur once
     *      $obj->repeatEveryWeek(10, 0) will occur once
     *      $obj->repeatEveryWeek(10, 4) will occur every 10 weeks (70days) 5 times
     *
     * @param int $frequency    Frequency the object will be repeated
     * @param int $nbRepetition Time the object will be repeated
     *
     * @return $this
     */
    public function repeatEveryWeek($frequency = 1, $nbRepetition = 0)
    {
        $this->_repeatEveryPeriod($period = 'W', $frequency, $nbRepetition);

        return $this;
    }

    /**
     * Set the Object to be repeated every month
     * Ex : $obj->repeatEveryWeek() will occur once
     *      $obj->repeatEveryWeek(10) will occur once
     *      $obj->repeatEveryWeek(10, 0) will occur once
     *      $obj->repeatEveryWeek(10, 4) will occur every 10 month (70days) 5times
     *
     * @param int $frequency    Frequency the object will be repeated
     * @param int $nbRepetition Time the object will be repeated
     *
     * @return $this
     */
    public function repeatEveryMonth($frequency = 1, $nbRepetition = 0)
    {
        $this->_repeatEveryPeriod($period = 'M', $frequency, $nbRepetition);

        return $this;
    }

    /**
     * Set the Object to be repeated every year
     * Ex : $obj->repeatEveryWeek() will occur once
     *      $obj->repeatEveryWeek(10) will occur once
     *      $obj->repeatEveryWeek(10, 0) will occur once
     *      $obj->repeatEveryWeek(10, 4) will occur every 10 year 5 times
     *
     * @param int $frequency    Frequency the object will be repeated
     * @param int $nbRepetition Time the object will be repeated
     *
     * @return $this
     */
    public function repeatEveryYear($frequency = 1, $nbRepetition = 0)
    {
        $this->_repeatEveryPeriod($period = 'Y', $frequency, $nbRepetition);

        return $this;
    }

    /**
     * Set the Object to be repeated every Period
     * Ex : $obj->repeatEveryPeriod('D') will occur once
     *      $obj->repeatEveryPeriod('W', 10) will occur once
     *      $obj->repeatEveryPeriod('W', 10, 0) will occur once
     *      $obj->repeatEveryPeriod('M', 10, 4) will occur every 10 month 5 times
     *
     * @param string $period       Period Y|M||D|W
     * @param int    $frequency    Frequency the object will be repeated
     * @param int    $nbRepetition Time the object will be repeated
     *
     * @return $this
     */
    private function _repeatEveryPeriod($period, $frequency = 1, $nbRepetition = 0)
    {
        if (is_numeric($frequency) && $frequency > 0) {
            $this->interval = new \DateInterval('P' . $frequency . $period);
        }

        if (is_numeric($nbRepetition) && $nbRepetition >= 0) {
            $this->recurrences = $nbRepetition;
        }

        $this->generateDatePeriod();

        return $this;
    }



    /**
     * Set Start time
     *
     * @param \DateTime $from Start time
     *
     * @return $this
     */
    public function setFrom($from)
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Get Start time
     *
     * @return \DateTime
     */
    public function getFrom()
    {
        return clone $this->from;
    }

    /**
     * Set DatePeriod
     *
     * @param DatePeriod $datePeriod DatePeriod
     *
     * @return $this
     */
    public function setDatePeriod(DatePeriod $datePeriod)
    {
        $this->datePeriod = $datePeriod;

        return $this;
    }

    /**
     * Get date DatePeriod
     *
     * @return \DatePeriod
     */
    public function getDatePeriod()
    {
        return clone $this->datePeriod;
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