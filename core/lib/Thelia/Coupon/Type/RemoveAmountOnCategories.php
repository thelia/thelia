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
use Thelia\Model\Category;

/**
 * Allow to remove an amount from the checkout total
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RemoveAmountOnCategories extends CouponAbstract
{
    const CATEGORIES_LIST = 'categories';

    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.remove_amount_on_categories';

    public $category_list = array();

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

        $this->category_list = isset($effects[self::CATEGORIES_LIST]) ? $effects[self::CATEGORIES_LIST] : array();

        if (! is_array($this->category_list)) $this->category_list = array($this->category_list);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Fixed amount discount for selected categories', array(), 'coupon');
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->facade
            ->getTranslator()
            ->trans(
                'This coupon subtracts the specified amount from the order total for each product which belongs to the selected categories. If the discount is greater than the total order, the customer will only pay the shipping, or nothing if the coupon also provides free shipping.',
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
                        $discount += $cartItem->getQuantity() * $this->amount;

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
    public function drawBackOfficeInputs()
    {
        return $this->facade->getParser()->render('coupon/type-fragments/remove-amount-on-categories.html', [

            // The standard "Amount" field
            'amount_field_name'     => $this->makeCouponFieldName(self::AMOUNT_FIELD_NAME),
            'amount_value'          => $this->amount,

            // The categories list field
            'categories_field_name' => $this->makeCouponFieldName(self::CATEGORIES_LIST),
            'categories_values'     => $this->category_list
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function getFieldList()
    {
        return [self::AMOUNT_FIELD_NAME, self::CATEGORIES_LIST];
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
        } elseif ($fieldName === self::CATEGORIES_LIST) {
            if (empty($fieldValue)) {
                throw new \InvalidArgumentException(
                    Translator::getInstance()->trans(
                        'Please select at least one category'
                    )
                );
            }
        }

        return $fieldValue;
    }

}
