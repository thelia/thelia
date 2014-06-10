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
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RemoveAmountOnProducts extends CouponAbstract
{
    const CATEGORY_ID   = 'category_id';
    const PRODUCTS_LIST = 'products';

    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.remove_amount_on_products';

    public $category_id  = 0;
    public $product_list = array();

    /**
     * @inheritdoc
     */
    public function set(
        FacadeInterface $facade,
        $code,
        $title,
        $shortDescription,
        $description,
        array $effects,
        $isCumulative,
        $isRemovingPostage,
        $isAvailableOnSpecialOffers,
        $isEnabled,
        $maxUsage,
        \DateTime $expirationDate,
        $freeShippingForCountries,
        $freeShippingForModules,
        $perCustomerUsageCount
    )
    {
        parent::set(
            $facade, $code, $title, $shortDescription, $description, $effects,
            $isCumulative, $isRemovingPostage, $isAvailableOnSpecialOffers, $isEnabled, $maxUsage, $expirationDate,
            $freeShippingForCountries,
            $freeShippingForModules,
            $perCustomerUsageCount
        );

        $this->product_list = isset($effects[self::PRODUCTS_LIST]) ? $effects[self::PRODUCTS_LIST] : array();

        if (! is_array($this->product_list)) $this->product_list = array($this->product_list);

        $this->category_id = isset($effects[self::CATEGORY_ID]) ? $effects[self::CATEGORY_ID] : 0;

        return $this;
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
    public function exec()
    {
        // This coupon subtracts the specified amount from the order total
        // for each product of the selected products.
        $discount = 0;

        $cartItems = $this->facade->getCart()->getCartItems();

        /** @var CartItem $cartItem */
        foreach ($cartItems as $cartItem) {

            if (in_array($cartItem->getProduct()->getId(), $this->product_list)) {
                if (! $cartItem->getPromo() || $this->isAvailableOnSpecialOffers()) {
                    $discount += $cartItem->getQuantity() * $this->amount;
                }
            }
        }

        return $discount;
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        return $this->facade->getParser()->render('coupon/type-fragments/remove-amount-on-products.html', [

            // The standard "Amount" field
            'amount_field_name'     => $this->makeCouponFieldName(self::AMOUNT_FIELD_NAME),
            'amount_value'          => $this->amount,

            // The category ID field
            'category_id_field_name' => $this->makeCouponFieldName(self::CATEGORY_ID),
            'category_id_value'     => $this->category_id,

            // The products list field
            'products_field_name' => $this->makeCouponFieldName(self::PRODUCTS_LIST),
            'products_values'     => $this->product_list,
            'products_values_csv' => implode(', ', $this->product_list)
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function getFieldList()
    {
        return [self::AMOUNT_FIELD_NAME, self::CATEGORY_ID, self::PRODUCTS_LIST];
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
                        'Value %val for Discount Amount is invalid. Please enter a positive value.',
                        [ '%val' => $fieldValue]
                    )
                );
            }
        } elseif ($fieldName === self::CATEGORY_ID) {
            if (empty($fieldValue)) {
                throw new \InvalidArgumentException(
                    Translator::getInstance()->trans(
                        'Please select a category'
                    )
                );
            }
        } elseif ($fieldName === self::PRODUCTS_LIST) {
            if (empty($fieldValue)) {
                throw new \InvalidArgumentException(
                    Translator::getInstance()->trans(
                        'Please select at least one product'
                    )
                );
            }
        }

        return $fieldValue;
    }
}
