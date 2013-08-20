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

use Thelia\Type\IntType;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Check a Checkout against its Product number
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class AvailableForXArticles extends CouponRuleAbstract
{
    /**
     * @inheritdoc
     */
    public function checkBackOfficeIntput()
    {
        $ret = false;
        $validator = new IntType();
        $firstParam = reset($this->validators);
        if ($firstParam) {
            $ret = $validator->isValid($firstParam);
        }

        return $ret;
    }

    public function checkCheckoutInput()
    {
        $ret = false;
        $validator = new IntType();
        $firstParam = reset($this->validated);
        if ($firstParam) {
            $ret = $validator->isValid($firstParam);
        }

        return $ret;
    }

    public function isMatching()
    {
        if ($this->checkBackOfficeIntput() && $this->checkCheckoutInput()) {
            $firstValidatorsParam = reset($this->validators);
            $firstValidatedParam = reset($this->validated);
//            if($firstValidatedParam >= $firstValidatedParam)
        }
    }

}