<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Form;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CouponQuery;

/**
 * Class CouponCode.
 *
 * Manage how a coupon is entered by a customer
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CouponCode extends BaseForm
{
    /**
     * Build form.
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'coupon-code',
                TextType::class,
                [
                    'required' => true,
                    'constraints' => [
                        new Constraints\NotBlank(),
                        new Constraints\Callback([$this, 'verifyExistingCode']),
                    ],
                ]
            )
        ;
    }

    public function verifyExistingCode($value, ExecutionContextInterface $context)
    {
        $coupon = CouponQuery::create()
            ->filterByCode($value, Criteria::EQUAL)
            ->findOne();

        if (null === $coupon) {
            $context->addViolation(Translator::getInstance()->trans('This coupon does not exists'));
        }
    }

    /**
     * Form name.
     *
     * @return string
     */
    public static function getName()
    {
        return 'thelia_coupon_code';
    }
}
