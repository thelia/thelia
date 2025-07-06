<?php

declare(strict_types=1);

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
     */
    abstract public function setFieldsValue(array $effects);

    /**
     * Get the discount for a specific cart item.
     *
     * @param CartItem $cartItem the cart item
     *
     * @return float the discount value
     */
    abstract public function getCartItemDiscount(CartItem $cartItem): float;

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
        DateTime $expirationDate,
        $freeShippingForCountries,
        $freeShippingForModules,
        $perCustomerUsageCount,
    ): static {
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

    public function drawBaseBackOfficeInputs(string $templateName, array $otherFields): string
    {
        return $this->facade->getParser()->render($templateName, $otherFields);
    }

    public function getBaseFieldList($otherFields): array
    {
        return array_merge($otherFields);
    }

    public function checkBaseCouponFieldValue(string $fieldName, string $fieldValue): string
    {
        return $fieldValue;
    }
}
