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

use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CouponQuery;

/**
 * Class CouponCode
 *
 * Manage how a coupon is entered by a customer
 *
 * @package Thelia\Form
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CouponCode extends BaseForm
{
    /**
     * Build form
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                "coupon-code",
                "text",
                [
                    "required"    => true,
                    "constraints" => [
                        new Constraints\NotBlank(),
                        new Constraints\Callback([
                            "methods" => [
                                [$this, "verifyExistingCode"],
                            ],
                        ]),
                    ]
                ]
            )
        ;
    }

    public function verifyExistingCode($value, ExecutionContextInterface $context)
    {
        $coupon = CouponQuery::create()->findOneByCode($value);
        if (null === $coupon) {
            $context->addViolation(Translator::getInstance()->trans("This coupon does not exists"));
        }
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
