<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Coupon\Rule;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Assist in writing a condition of whether the Rule is applied or not
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponRuleAbstract implements CuponRuleInterface
{
    /** @var array Parameters validating $validated against */
    protected $validators = array();

    /** @var array Parameters to be validated */
    protected $validated = array();

    /**
     * Constructor
     *
     * @param array $validators Parameters validating $validated against
     * @param array $validated  Parameters to be validated
     */
    public function __construct(array $validators, array $validated)
    {
        $this->validators = $validators;
        $this->validated = $validated;
    }

    /**
     * Check if backoffice inputs are relevant or not
     *
     * @return bool
     */
    public function checkBackOfficeIntput()
    {
        // TODO: Implement checkBackOfficeIntput() method.
    }

    /**
     * Check if Checkout inputs are relevant or not
     *
     * @return bool
     */
    public function checkCheckoutInput()
    {
        // TODO: Implement checkCheckoutInput() method.
    }

    /**
     * Check if the current Checkout matchs this condition
     *
     * @return bool
     */
    public function isMatching()
    {
        // TODO: Implement isMatching() method.
    }

}