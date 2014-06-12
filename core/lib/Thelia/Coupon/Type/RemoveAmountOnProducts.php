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
use Thelia\Coupon\FacadeInterface;
use Thelia\Model\CartItem;
use Thelia\Model\Product;

/**
 * Allow to remove an amount from the checkout total
 *
 * @package Coupon
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class RemoveAmountOnProducts extends AbstractRemoveOnProducts
{
    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.remove_amount_on_products';

    /**
     * @inheritdoc
     */
    protected function setFieldsValue($effects) {
        // Nothing to do: amount is processed by CouponAbstract
    }

    /**
     * @inheritdoc
     */
    protected function getCartItemDiscount($cartItem) {
        return $cartItem->getQuantity() * $this->amount;
    }

    /**
     * Get I18n name
     *
     * @return string
     */
    public function getName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Fixed amount discount for selected products', array(), 'coupon');
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->facade
            ->getTranslator()
            ->trans(
                'This coupon subtracts the specified amount from the order total for each selected product. If the discount is greater than the total order, the customer will only pay the shipping, or nothing if the coupon also provides free shipping.',
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
        return $this->drawBaseBackOfficeInputs('coupon/type-fragments/remove-amount-on-products.html', [
            'amount_field_name' => $this->makeCouponFieldName(self::AMOUNT_FIELD_NAME),
            'amount_value'      => $this->amount
        ]);
     }

    /**
     * @inheritdoc
     */
    protected function getFieldList()
    {
        return  $this->getBaseFieldList([self::AMOUNT_FIELD_NAME]);
    }

    /**
     * @inheritdoc
     */
    protected function checkCouponFieldValue($fieldName, $fieldValue)
    {
        $this->checkBaseCouponFieldValue($fieldName, $fieldValue);

        if ($fieldName === self::AMOUNT_FIELD_NAME) {

            if (floatval($fieldValue) < 0) {
                throw new \InvalidArgumentException(
                    Translator::getInstance()->trans(
                        'Value %val for Discount Amount is invalid. Please enter a positive value.',
                        [ '%val' => $fieldValue]
                    )
                );
            }
        }

        return $fieldValue;
    }
}