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

/**
 * Allow to remove an amount from the checkout total
 *
 * @package Coupon
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
abstract class AbstractRemoveOnProducts extends CouponAbstract implements AmountAndPercentageCouponInterface
{
    const CATEGORY_ID   = 'category_id';
    const PRODUCTS_LIST = 'products';

    public $category_id  = 0;
    public $product_list = array();

    /**
     * Set the value of specific coupon fields.
     *
     * @param array $effects the Coupon effects params
     */
    abstract public function setFieldsValue($effects);

    /**
     * Get the discount for a specific cart item.
     *
     * @param  CartItem $cartItem the cart item
     * @return float    the discount value
     */
    abstract public function getCartItemDiscount(CartItem $cartItem);

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
    ) {
        parent::set(
            $facade,
            $code,
            $title,
            $shortDescription,
            $description,
            $effects,
            $isCumulative,
            $isRemovingPostage,
            $isAvailableOnSpecialOffers,
            $isEnabled,
            $maxUsage,
            $expirationDate,
            $freeShippingForCountries,
            $freeShippingForModules,
            $perCustomerUsageCount
        );

        $this->product_list = isset($effects[self::PRODUCTS_LIST]) ? $effects[self::PRODUCTS_LIST] : array();

        if (! is_array($this->product_list)) {
            $this->product_list = array($this->product_list);
        }

        $this->category_id = isset($effects[self::CATEGORY_ID]) ? $effects[self::CATEGORY_ID] : 0;

        $this->setFieldsValue($effects);

        return $this;
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
                    $discount += $this->getCartItemDiscount($cartItem);
                }
            }
        }

        return $discount;
    }

    /**
     * @inheritdoc
     */
    public function drawBaseBackOfficeInputs($templateName, $otherFields)
    {
        return $this->facade->getParser()->render($templateName, array_merge($otherFields, [

            // The category ID field
            'category_id_field_name' => $this->makeCouponFieldName(self::CATEGORY_ID),
            'category_id_value'     => $this->category_id,

            // The products list field
            'products_field_name' => $this->makeCouponFieldName(self::PRODUCTS_LIST),
            'products_values'     => $this->product_list,
            'products_values_csv' => implode(', ', $this->product_list)
        ]));
    }

    /**
     * @inheritdoc
     */
    public function getBaseFieldList($otherFields)
    {
        return array_merge($otherFields, [self::CATEGORY_ID, self::PRODUCTS_LIST]);
    }

    /**
     * @inheritdoc
     */
    public function checkBaseCouponFieldValue($fieldName, $fieldValue)
    {
        if ($fieldName === self::CATEGORY_ID) {
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
