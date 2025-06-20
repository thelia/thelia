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


use InvalidArgumentException;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\FacadeInterface;
use Thelia\Model\AttributeCombination;
use Thelia\Model\CartItem;

/**
 * The base class to process a discount related to Attribute values.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
abstract class AbstractRemoveOnAttributeValues extends CouponAbstract implements AmountAndPercentageCouponInterface
{
    public const ATTRIBUTES_AV_LIST = 'attribute_avs';

    public const ATTRIBUTE = 'attribute_id';

    public array $attributeAvList = [];

    public int $attribute = 0;

    /**
     * Set the value of specific coupon fields.
     *
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
        $perCustomerUsageCount
    ): static
    {
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

        $this->attributeAvList = $effects[self::ATTRIBUTES_AV_LIST] ?? [];

        if (!\is_array($this->attributeAvList)) {
            $this->attributeAvList = [$this->attributeAvList];
        }

        $this->attribute = $effects[self::ATTRIBUTE] ?? 0;

        $this->setFieldsValue($effects);

        return $this;
    }

    public function exec(): float|int
    {
        // This coupon subtracts the specified amount from the order total
        // for each product which uses the selected attributes
        $discount = 0;

        $cartItems = $this->facade->getCart()->getCartItems();

        /** @var CartItem $cartItem */
        foreach ($cartItems as $cartItem) {
            if (!$cartItem->getPromo() || $this->isAvailableOnSpecialOffers()) {
                $productSaleElements = $cartItem->getProductSaleElements();

                $combinations = $productSaleElements->getAttributeCombinations();

                /** @var AttributeCombination $combination */
                foreach ($combinations as $combination) {
                    $attrValue = $combination->getAttributeAvId();

                    if (\in_array($attrValue, $this->attributeAvList)) {
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
     */
    public function drawBaseBackOfficeInputs(string $templateName, array $otherFields): string
    {
        return $this->facade->getParser()->render($templateName, array_merge($otherFields, [
            // The attributes list field
            'attribute_field_name' => $this->makeCouponFieldName(self::ATTRIBUTE),
            'attribute_value' => $this->attribute,

            // The attributes list field
            'attribute_av_field_name' => $this->makeCouponFieldName(self::ATTRIBUTES_AV_LIST),
            'attribute_av_values' => $this->attributeAvList,
        ]));
    }

    public function getBaseFieldList($otherFields): array
    {
        return array_merge($otherFields, [self::ATTRIBUTE, self::ATTRIBUTES_AV_LIST]);
    }

    public function checkBaseCouponFieldValue(string $fieldName, string $fieldValue): string
    {
        if ($fieldName === self::ATTRIBUTE) {
            if ($fieldValue === '' || $fieldValue === '0') {
                throw new InvalidArgumentException(
                    Translator::getInstance()->trans(
                        'Please select an attribute'
                    )
                );
            }
        } elseif ($fieldName === self::ATTRIBUTES_AV_LIST) {
            if ($fieldValue === '' || $fieldValue === '0') {
                throw new InvalidArgumentException(
                    Translator::getInstance()->trans(
                        'Please select at least one attribute value'
                    )
                );
            }
        }

        return $fieldValue;
    }
}
