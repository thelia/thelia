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
 * A trait to manage a coupon which removes a constant amount from the order total.
 * Should be used on coupons classes which implements AmountAndPercentageCouponInterface.
 *
 * Class AmountCouponTrait
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
trait AmountCouponTrait
{
    /**
     * Should return the amount field name, defined in the parent class.
     */
    abstract protected function getAmountFieldName(): string;

    public function setFieldsValue(array $effects): void
    {
        $this->amount = $effects[$this->getAmountFieldName()];
    }

    public function getCartItemDiscount(CartItem $cartItem): float
    {
        return $cartItem->getQuantity() * $this->amount;
    }

    public function callDrawBackOfficeInputs($templateName): string
    {
        return $this->drawBaseBackOfficeInputs($templateName, [
            'amount_field_name' => $this->makeCouponFieldName($this->getAmountFieldName()),
            'amount_value' => $this->amount,
        ]);
    }

    protected function getFieldList(): array
    {
        return $this->getBaseFieldList([$this->getAmountFieldName()]);
    }

    protected function checkCouponFieldValue(string $fieldName, string $fieldValue): string
    {
        $this->checkBaseCouponFieldValue($fieldName, $fieldValue);

        if ($fieldName === $this->getAmountFieldName() && (float) $fieldValue < 0) {
            throw new \InvalidArgumentException(Translator::getInstance()->trans('Value %val for Discount Amount is invalid. Please enter a positive value.', ['%val' => $fieldValue]));
        }

        return $fieldValue;
    }
}
