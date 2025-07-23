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
use Thelia\Model\Category;

/**
 * Allow to remove an amount from the checkout total.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
abstract class AbstractRemoveOnCategories extends CouponAbstract implements AmountAndPercentageCouponInterface
{
    public const CATEGORIES_LIST = 'categories';

    protected array $category_list = [];

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

        $this->category_list = $effects[self::CATEGORIES_LIST] ?? [];

        if (!\is_array($this->category_list)) {
            $this->category_list = [$this->category_list];
        }

        $this->setFieldsValue($effects);

        return $this;
    }

    public function exec(): float
    {
        // This coupon subtracts the specified amount from the order total
        // for each product of the selected categories.
        $discount = 0;

        $cartItems = $this->facade->getCart()->getCartItems();

        /** @var CartItem $cartItem */
        foreach ($cartItems as $cartItem) {
            if (!$cartItem->getPromo() || $this->isAvailableOnSpecialOffers()) {
                $categories = $cartItem->getProduct()->getCategories();

                /** @var Category $category */
                foreach ($categories as $category) {
                    if (\in_array($category->getId(), $this->category_list, true)) {
                        $discount += $this->getCartItemDiscount($cartItem);

                        break;
                    }
                }
            }
        }

        return (float) $discount;
    }

    public function drawBaseBackOfficeInputs(string $templateName, array $otherFields): string
    {
        return $this->facade->getParser()->render($templateName, array_merge($otherFields, [
            // The categories list field
            'categories_field_name' => $this->makeCouponFieldName(self::CATEGORIES_LIST),
            'categories_values' => $this->category_list,
        ]));
    }

    public function getBaseFieldList($otherFields): array
    {
        return array_merge($otherFields, [self::CATEGORIES_LIST]);
    }

    public function checkBaseCouponFieldValue(string $fieldName, string $fieldValue): string
    {
        if (self::CATEGORIES_LIST === $fieldName && ('' === $fieldValue || '0' === $fieldValue)) {
            throw new \InvalidArgumentException(Translator::getInstance()->trans('Please select at least one category'));
        }

        return $fieldValue;
    }
}
