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
class RemovePercentageOnProducts extends AbstractRemoveOnProducts
{
   const PERCENTAGE    = 'percentage';

    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.remove_percentage_on_products';

    public $percentage = 0;

    /**
     * @inheritdoc
     */
    protected function setFieldsValue($effects) {

        $this->percentage = $effects[self::PERCENTAGE];
    }

    /**
     * @inheritdoc
     */
    protected function getCartItemDiscount($cartItem) {
        return $cartItem->getQuantity() * $cartItem->getPrice() * $this->percentage;
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
            ->trans('Percentage discount for selected products', array(), 'coupon');
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->facade
            ->getTranslator()
            ->trans(
                'This coupon subtracts from the order total the specified percentage of each selected product price. If the discount is greater than the total order, the customer will only pay the shipping, or nothing if the coupon also provides free shipping.',
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
        return $this->drawBaseBackOfficeInputs('coupon/type-fragments/remove-percentage-on-products.html', [
                'percentage_field_name'  => $this->makeCouponFieldName(self::PERCENTAGE),
                'percentage_value'       => $this->percentage,
            ]);
    }

    /**
     * @inheritdoc
     */
    protected function getFieldList()
    {
        return  $this->getBaseFieldList([self::PERCENTAGE]);
    }

    /**
     * @inheritdoc
     */
    protected function checkCouponFieldValue($fieldName, $fieldValue)
    {
        $this->checkBaseCouponFieldValue($fieldName, $fieldValue);

        if ($fieldName === self::PERCENTAGE) {

            $pcent = floatval($fieldValue);

            if ($pcent <= 0 || $pcent > 100) {
                throw new \InvalidArgumentException(
                    Translator::getInstance()->trans(
                        'Value %val for Percent Discount is invalid. Please enter a positive value between 1 and 100.',
                        [ '%val' => $fieldValue]
                    )
                );
            }
        }

        return $fieldValue;
    }
}