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
use Thelia\Model\CartItem;

/**
 * A trait to manage a coupon which removes a percentage of cart items from the order total.
 * Should be used on coupons classes which implements AmountAndPercentageCouponInterface
 *
 * Class PercentageCouponTrait
 * @author Franck Allimant <franck@cqfdev.fr>
 * @package Thelia\Coupon\Type
 */
trait PercentageCouponTrait
{
    public $percentage = 0;

    /**
     * Should return the percentage field name, defined in the parent class.
     *
     * @return string the percentage field name
     */
    abstract protected function getPercentageFieldName();

    /**
     * @inheritdoc
     */
    public function setFieldsValue($effects)
    {
        $this->percentage = $effects[$this->getPercentageFieldName()];
    }

    /**
     * @inheritdoc
     */
    public function getCartItemDiscount(CartItem $cartItem)
    {
        return $cartItem->getQuantity() * $cartItem->getRealTaxedPrice($this->facade->getDeliveryCountry()) * ($this->percentage / 100);
    }

    /**
     * @inheritdoc
     */
    public function callDrawBackOfficeInputs($templateName)
    {
        return $this->drawBaseBackOfficeInputs($templateName, [
                'percentage_field_name'  => $this->makeCouponFieldName($this->getPercentageFieldName()),
                'percentage_value'       => $this->percentage,
            ]);
    }

    /**
     * @inheritdoc
     */
    protected function getFieldList()
    {
        return  $this->getBaseFieldList([$this->getPercentageFieldName()]);
    }

    /**
     * @inheritdoc
     */
    protected function checkCouponFieldValue($fieldName, $fieldValue)
    {
        $this->checkBaseCouponFieldValue($fieldName, $fieldValue);

        if ($fieldName === $this->getPercentageFieldName()) {
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
