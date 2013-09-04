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

namespace Thelia\Form;

use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/29/13
 * Time: 3:45 PM
 *
 * Allow to build a form Coupon
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponCreationForm extends BaseForm
{
    /**
     * Build Coupon form
     *
     * @return void
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'code',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            )
            ->add(
                'title',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            )
            ->add(
                'shortDescription',
                'text',
                array(
                    'invalid_message' => 'test',
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            )
            ->add(
                'description',
                'textarea',
                array(
                    'invalid_message' => 'test',
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            )
            ->add(
                'effect',
                'text',
                array(
                    'invalid_message' => 'test',
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            )
            ->add(
                'amount',
                'money',
                array()
            )
            ->add(
                'isEnabled',
                'checkbox',
                array()
            )
            ->add(
                'expirationDate',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            )
            ->add(
                'isCumulative',
                'checkbox',
                array()
            )
            ->add(
                'isRemovingPostage',
                'checkbox',
                array()
            )
            ->add(
                'isAvailableOnSpecialOffers',
                'checkbox',
                array()
            )
            ->add(
                'maxUsage',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            );
    }

    /**
     * Get form name
     *
     * @return string
     */
    public function getName()
    {
        return 'thelia_coupon_creation';
    }
}
