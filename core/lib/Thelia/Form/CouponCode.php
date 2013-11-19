<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
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
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Form;

use Symfony\Component\Validator\Constraints;

/**
 * Class CouponCode
 *
 * Manage how a coupon is entered by a customer
 *
 * @package Thelia\Form
 * @author Guillaume MOREL <gmorel@openstudio.fr>
 */
class CouponCode extends BaseForm
{
    /**
     * Build form
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add("coupon-code", "text", array(
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                )
            )
        );
    }

    /**
     * Form name
     *
     * @return string
     */
    public function getName()
    {
        return "thelia_coupon_code";
    }
}
