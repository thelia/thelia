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
 * Allow to validate parameters
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RuleValidator
{
    /** @var string Operator ex: Operators::INFERIOR */
    protected $operator = null;

    /** @var ComparableInterface Validator */
    protected $param = null;

    /**
     * Constructor
     *
     * @param string              $operator Operator ex: Operators::INFERIOR
     * @param ComparableInterface $param    Validator ex: PriceParam
     */
    function __construct($operator, ComparableInterface $param)
    {
        $this->operator = $operator;
        $this->param = $param;
    }

    /**
     * Get Validator Operator
     *
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * Get Validator Param
     *
     * @return ComparableInterface
     */
    public function getParam()
    {
        return $this->param;
    }

}