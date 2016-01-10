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

use Thelia\Coupon\FacadeInterface;
use Thelia\Model\CartItem;
use Thelia\Model\Category;

/**
 * Allow to remove an amount from the checkout total
 *
 * @package Coupon
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
abstract class AbstractRemove extends CouponAbstract implements AmountAndPercentageCouponInterface
{
    /**
     * Set the value of specific coupon fields.
     *
     * @param Array $effects the Coupon effects params
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

        $this->setFieldsValue($effects);

        return $this;
    }
    /**
     * @inheritdoc
     */
    public function exec()
    {
        // This coupon subtracts the specified amount from the order total
        // for each product of the selected categories.
        $discount = 0;

        $cartItems = $this->facade->getCart()->getCartItems();

        /** @var CartItem $cartItem */
        foreach ($cartItems as $cartItem) {
            if (! $cartItem->getPromo() || $this->isAvailableOnSpecialOffers()) {
                $categories = $cartItem->getProduct()->getCategories();

                /** @var Category $category */
                foreach ($categories as $category) {
                    if (in_array($category->getId(), $this->category_list)) {
                        $discount += $this->getCartItemDiscount($cartItem);

                        break;
                    }
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
        return $this->facade->getParser()->render($templateName, $otherFields);
    }

    /**
     * @inheritdoc
     */
    public function getBaseFieldList($otherFields)
    {
        return array_merge($otherFields);
    }

    /**
     * @inheritdoc
     */
    public function checkBaseCouponFieldValue($fieldName, $fieldValue)
    {
        return $fieldValue;
    }
}
