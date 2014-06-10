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

namespace Thelia\Coupon\Type;

use Thelia\Core\Translation\Translator;

/**
 * Allow to remove an amount from the checkout total
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RemoveXAmount extends CouponAbstract
{
    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.remove_x_amount';

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Fixed Amount Discount', array(), 'coupon');
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->facade
            ->getTranslator()
            ->trans(
                'This coupon will subtracts a set amount from the total cost of an order. If the discount is greater than the total order corst, the customer will only pay the shipping, or nothing if the coupon also provides free shipping.',
                array(),
                'coupon'
            );

        return $toolTip;
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        return $this->facade->getParser()->render('coupon/type-fragments/remove-x-amount.html', [
                'fieldName' => $this->makeCouponFieldName(self::AMOUNT_FIELD_NAME),
                'value'     => $this->amount
            ]);
    }

    /**
     * @inheritdoc
     */
    protected function getFieldList()
    {
        return [self::AMOUNT_FIELD_NAME];
    }

    /**
     * @inheritdoc
     */
    protected function checkCouponFieldValue($fieldName, $fieldValue)
    {
        if ($fieldName === self::AMOUNT_FIELD_NAME) {

            if (floatval($fieldValue) < 0) {
                throw new \InvalidArgumentException(
                    Translator::getInstance()->trans(
                        'Value %val for Disount Amount is invalid. Please enter a positive value.',
                        [ '%val' => $fieldValue]
                    )
                );
            }
        }

        return $fieldValue;
    }

}
