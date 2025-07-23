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

use Thelia\Core\Translation\Translator;
use Thelia\Coupon\FacadeInterface;
use Thelia\Model\CartItem;

/**
 * Allow to remove an amount from the checkout total.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
abstract class AbstractRemoveOnProducts extends CouponAbstract implements AmountAndPercentageCouponInterface
{
    public const CATEGORY_ID = 'category_id';
    public const PRODUCTS_LIST = 'products';

    public int $category_id = 0;
    public array $product_list = [];

    /**
     * Set the value of specific coupon fields.
     */
    abstract public function setFieldsValue(array $effects);

    /**
     * Get the discount for a specific cart item.
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
            $perCustomerUsageCount,
        );

        $this->product_list = $effects[self::PRODUCTS_LIST] ?? [];

        $this->category_id = $effects[self::CATEGORY_ID] ?? 0;

        $this->setFieldsValue($effects);

        return $this;
    }

    public function exec(): float
    {
        // This coupon subtracts the specified amount from the order total
        // for each product of the selected products.
        $discount = 0;

        $cartItems = $this->facade->getCart()->getCartItems();

        /** @var CartItem $cartItem */
        foreach ($cartItems as $cartItem) {
            if (\in_array($cartItem->getProduct()->getId(), $this->product_list, true) && (!$cartItem->getPromo() || $this->isAvailableOnSpecialOffers())) {
                $discount += $this->getCartItemDiscount($cartItem);
            }
        }

        return (float) $discount;
    }

    public function drawBaseBackOfficeInputs(string $templateName, array $otherFields): string
    {
        return $this->facade->getParser()->render($templateName, array_merge($otherFields, [
            // The category ID field
            'category_id_field_name' => $this->makeCouponFieldName(self::CATEGORY_ID),
            'category_id_value' => $this->category_id,

            // The products list field
            'products_field_name' => $this->makeCouponFieldName(self::PRODUCTS_LIST),
            'products_values' => $this->product_list,
            'products_values_csv' => implode(', ', $this->product_list),
        ]));
    }

    public function getBaseFieldList($otherFields): array
    {
        return array_merge($otherFields, [self::CATEGORY_ID, self::PRODUCTS_LIST]);
    }

    public function checkBaseCouponFieldValue(string $fieldName, string $fieldValue): string
    {
        if (self::CATEGORY_ID === $fieldName) {
            if ('' === $fieldValue || '0' === $fieldValue) {
                throw new \InvalidArgumentException(Translator::getInstance()->trans('Please select a category'));
            }
        } elseif (self::PRODUCTS_LIST === $fieldName) {
            if ('' === $fieldValue || '0' === $fieldValue) {
                throw new \InvalidArgumentException(Translator::getInstance()->trans('Please select at least one product'));
            }
        }

        return $fieldValue;
    }
}
