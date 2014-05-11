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
     * Get I18n name
     *
     * @return string
     */
    public function getName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Fixed Amount Discount', array(), 'coupon');
    }

    /**
     * Get I18n amount input name
     *
     * @return string
     */
    public function getInputName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Discount amount', array(), 'coupon');
    }

    /**
     * Get I18n tooltip
     *
     * @return string
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

    public function drawBackOfficeInputs()
    {
        return $this->facade->getParser()->render('coupon/type-fragments/remove-x-amount.html', [
                'label'     => $this->getInputName(),
                'fieldName' => self::INPUT_AMOUNT_NAME,
                'value'     => $this->amount
            ]);
    }
}
