<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Form;

use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotEqualTo;

/**
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
                'text'

            )
            ->add(
                'description',
                'textarea'

            )
            ->add(
                'type',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new NotEqualTo(
                            array(
                                'value' => -1
                            )
                        )
                    )
                )
            )
            ->add(
                'amount',
                'money',
                array(
                    'constraints' => array(
                    new NotBlank()
                ))
            )
            ->add(
                'isEnabled',
                'text',
                array()
            )
            ->add(
                'expirationDate',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new Date()
                    )
                )
            )
            ->add(
                'isCumulative',
                'text',
                array()
            )
            ->add(
                'isRemovingPostage',
                'text',
                array()
            )
            ->add(
                'isAvailableOnSpecialOffers',
                'text',
                array()
            )
            ->add(
                'maxUsage',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(
                            array(
                                'value' => -1
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
        return 'thelia_coupon_creation';
    }
}
