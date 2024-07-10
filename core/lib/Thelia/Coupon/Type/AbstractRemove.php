<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Coupon\Type;

use Thelia\Coupon\FacadeInterface;
use Thelia\Model\CartItem;

/**
 * Allow to remove an amount from the checkout total.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
abstract class AbstractRemove extends CouponAbstract implements AmountAndPercentageCouponInterface
{
    /**
     * Set the value of specific coupon fields.
     *
     * @param array $effects the Coupon effects params
     */
    abstract public function setFieldsValue($effects);

    /**
     * Get the discount for a specific cart item.
     *
     * @param CartItem $cartItem the cart item
     *
     * @return float the discount value
     */
    abstract public function getCartItemDiscount(CartItem $cartItem);

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

    public function drawBaseBackOfficeInputs($templateName, $otherFields)
    {
        return $this->facade->getParser()->render($templateName, $otherFields);
    }

    public function getBaseFieldList($otherFields)
    {
        return array_merge($otherFields);
    }

    public function checkBaseCouponFieldValue($fieldName, $fieldValue)
    {
        return $fieldValue;
    }
}
