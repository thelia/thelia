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

use Thelia\Model\CartItem;

/**
 * Represents a Coupon ready to be processed in a Checkout process
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
interface AmountAndPercentageCouponInterface
{
    /**
     * Set the value of specific coupon fields.
     * @param array $effects the Coupon effects params
     */
    public function setFieldsValue($effects);

    /**
     * Get the discount for a specific cart item.
     *
     * @param  CartItem $cartItem the cart item
     * @return float    the discount value
     */
    public function getCartItemDiscount(CartItem $cartItem);

    /**
     * Renders the template which implements coupon specific user-input,
     * using the provided template file, and a list of specific input fields.
     *
     * @param string $templateName the path to the template
     * @param array  $otherFields  the list of additional fields fields
     *
     * @return string the rendered template.
     */
    public function drawBaseBackOfficeInputs($templateName, $otherFields);

    /**
     * @inheritdoc
     */
    public function getBaseFieldList($otherFields);

    /**
     * Check the value of a coupon configuration field
     *
     * @param string $fieldName
     * @param string $fieldValue
     * @return string the field value
     *
     * @throws \InvalidArgumentException is field value is not valid.
     */
    public function checkBaseCouponFieldValue($fieldName, $fieldValue);
}
