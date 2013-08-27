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
 * Represent a DateTime
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class DateParam extends RuleParameterAbstract
{
    /** @var \DateTime Date  */
    protected $dateTime = null;

    /**
     * Constructor
     *
     * @param CouponAdapterInterface $adapter  Provide necessary value from Thelia
     * @param \DateTime              $dateTime DateTime
     */
    public function __construct(CouponAdapterInterface $adapter, \DateTime $dateTime)
    {
        $this->dateTime = $dateTime;
        $this->adapter = $adapter;
    }

    /**
     * Get DateTime
     *
     * @return \DateTime
     */
    public function getDateTime()
    {
        return clone $this->dateTime;
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
            throw new \InvalidArgumentException('DateParam can compare only DateTime');
        }

        $ret = -1;
        if ($this->dateTime == $other) {
            $ret = 0;
        } elseif ($this->dateTime > $other) {
            $ret = 1;
        } else {
            $ret = -1;
        }

        return $ret;
    }

    /**
     * Get Parameter value to test against
     *
     * @return \Datetime
     */
    public function getValue()
    {
        return clone $this->dateTime;
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
