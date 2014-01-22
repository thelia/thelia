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

use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/29/13
 * Time: 3:45 PM
 *
 * Allow to build a form Install Step 3 Database connection
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class InstallStep3Form extends BaseForm
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
                'host',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            )
            ->add(
                'user',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            )
            ->add(
                'password',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            )
            ->add(
                'port',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThan(
                            array(
                                'value' => 0
                            )
                        )
                    )
                )
            )
            ->add(
                'locale',
                'hidden',
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
        return 'thelia_install_step3';
    }
}
