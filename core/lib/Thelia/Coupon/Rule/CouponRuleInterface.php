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

namespace Thelia\Coupon\Rule;

use Thelia\Coupon\CouponAdapterInterface;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Represents a condition of whether the Rule is applied or not
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
interface CouponRuleInterface
{
    /**
     * Check if backoffice inputs are relevant or not
     *
     * @return bool
     */
    public function checkBackOfficeInput();

    /**
     * Check if Checkout inputs are relevant or not
     *
     * @return bool
     */
    public function checkCheckoutInput();

    /**
     * Check if the current Checkout matches this condition
     *
     * @param CouponAdapterInterface $adapter allowing to gather
     *                               all necessary Thelia variables
     *
     * @return bool
     */
    public function isMatching(CouponAdapterInterface $adapter);

    /**
     * Return all available Operators for this Rule
     *
     * @return array Operators::CONST
     */
    public function getAvailableOperators();

}
