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
use Thelia\Model\AttributeCombination;
use Thelia\Model\CartItem;

/**
 * The base class to process a discount related to Attribute values.
 *
 * @package Coupon
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
abstract class AbstractRemoveOnAttributeValues extends CouponAbstract implements AmountAndPercentageCouponInterface
{
    const ATTRIBUTES_AV_LIST = 'attribute_avs';
    const ATTRIBUTE          = 'attribute_id';

    public $attributeAvList = array();
    public $attribute = 0;

    /**
     * Set the value of specific coupon fields.
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

        $this->attributeAvList = isset($effects[self::ATTRIBUTES_AV_LIST]) ? $effects[self::ATTRIBUTES_AV_LIST] : array();

        if (! is_array($this->attributeAvList)) {
            $this->attributeAvList = array($this->attributeAvList);
        }

        $this->attribute = isset($effects[self::ATTRIBUTE]) ? $effects[self::ATTRIBUTE] : 0;

        $this->setFieldsValue($effects);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function exec()
    {
        // This coupon subtracts the specified amount from the order total
        // for each product which uses the selected attributes
        $discount = 0;

        $cartItems = $this->facade->getCart()->getCartItems();

        /** @var CartItem $cartItem */
        foreach ($cartItems as $cartItem) {
            if (! $cartItem->getPromo() || $this->isAvailableOnSpecialOffers()) {
                $productSaleElements = $cartItem->getProductSaleElements();

                $combinations = $productSaleElements->getAttributeCombinations();

                /** @var AttributeCombination $combination */
                foreach ($combinations as $combination) {
                    $attrValue = $combination->getAttributeAvId();

                    if (in_array($attrValue, $this->attributeAvList)) {
                        $discount += $this->getCartItemDiscount($cartItem);

                        break;
                    }
                }
            }
        }

        return $discount;
    }

    /**
     * Renders the template which implements coupon specific user-input,
     * using the provided template file, and a list of specific input fields.
     *
     * @param string $templateName the path to the template
     * @param array  $otherFields  the list of additional fields fields
     *
     * @return string the rendered template.
     */
    public function drawBaseBackOfficeInputs($templateName, $otherFields)
    {
        return $this->facade->getParser()->render($templateName, array_merge($otherFields, [

            // The attributes list field
            'attribute_field_name' => $this->makeCouponFieldName(self::ATTRIBUTE),
            'attribute_value'      => $this->attribute,

            // The attributes list field
            'attribute_av_field_name' => $this->makeCouponFieldName(self::ATTRIBUTES_AV_LIST),
            'attribute_av_values'     => $this->attributeAvList
        ]));
    }

    /**
     * @inheritdoc
     */
    public function getBaseFieldList($otherFields)
    {
        return array_merge($otherFields, [self::ATTRIBUTE, self::ATTRIBUTES_AV_LIST]);
    }

    /**
     * @inheritdoc
     */
    public function checkBaseCouponFieldValue($fieldName, $fieldValue)
    {
        if ($fieldName === self::ATTRIBUTE) {
            if (empty($fieldValue)) {
                throw new \InvalidArgumentException(
                    Translator::getInstance()->trans(
                        'Please select an attribute'
                    )
                );
            }
        } elseif ($fieldName === self::ATTRIBUTES_AV_LIST) {
            if (empty($fieldValue)) {
                throw new \InvalidArgumentException(
                    Translator::getInstance()->trans(
                        'Please select at least one attribute value'
                    )
                );
            }
        }

        return $fieldValue;
    }
}
