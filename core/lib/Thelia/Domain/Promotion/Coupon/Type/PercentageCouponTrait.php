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

namespace Thelia\Domain\Promotion\Coupon\Type;

use Thelia\Core\Translation\Translator;
use Thelia\Model\CartItem;

/**
 * A trait to manage a coupon which removes a percentage of cart items from the order total.
 * Should be used on coupons classes which implements AmountAndPercentageCouponInterface.
 *
 * Class PercentageCouponTrait
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
trait PercentageCouponTrait
{
    public $percentage = 0;

    /**
     * Should return the percentage field name, defined in the parent class.
     *
     * @return string the percentage field name
     */
    abstract protected function getPercentageFieldName(): string;

    public function setFieldsValue(array $effects): void
    {
        $this->percentage = $effects[$this->getPercentageFieldName()];
    }

    public function getCartItemDiscount(CartItem $cartItem): float
    {
        return $cartItem->getTotalRealTaxedPrice($this->facade->getDeliveryCountry()) * ($this->percentage / 100);
    }

    public function callDrawBackOfficeInputs($templateName): string
    {
        return $this->drawBaseBackOfficeInputs($templateName, [
            'percentage_field_name' => $this->makeCouponFieldName($this->getPercentageFieldName()),
            'percentage_value' => $this->percentage,
        ]);
    }

    protected function getFieldList(): array
    {
        return $this->getBaseFieldList([$this->getPercentageFieldName()]);
    }

    protected function checkCouponFieldValue(string $fieldName, string $fieldValue): string
    {
        $this->checkBaseCouponFieldValue($fieldName, $fieldValue);

        if ($fieldName === $this->getPercentageFieldName()) {
            $pcent = (float) $fieldValue;

            if ($pcent <= 0 || $pcent > 100) {
                throw new \InvalidArgumentException(Translator::getInstance()->trans('Value %val for Percent Discount is invalid. Please enter a positive value between 1 and 100.', ['%val' => $fieldValue]));
            }
        }

        return $fieldValue;
    }
}
