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

use Thelia\Model\CartItem;

/**
 * Represents a Coupon ready to be processed in a Checkout process.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
interface AmountAndPercentageCouponInterface
{
    /**
     * Set the value of specific coupon fields.
     */
    public function setFieldsValue(array $effects);

    /**
     * Get the discount for a specific cart item.
     */
    public function getCartItemDiscount(CartItem $cartItem): float;

    /**
     * Renders the template which implements coupon specific user-input,
     * using the provided template file, and a list of specific input fields.
     */
    public function drawBaseBackOfficeInputs(string $templateName, array $otherFields): string;

    public function getBaseFieldList($otherFields);

    /**
     * Check the value of a coupon configuration field.
     *
     * @throws \InvalidArgumentException is field value is not valid
     */
    public function checkBaseCouponFieldValue(string $fieldName, string $fieldValue): string;
}
