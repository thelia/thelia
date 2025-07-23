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

/**
 * Allow to remove an amount from the checkout total.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class RemoveAmountOnAttributeValues extends AbstractRemoveOnAttributeValues
{
    use AmountCouponTrait;

    protected string $serviceId = 'thelia.coupon.type.remove_amount_on_attribute_av';

    protected function getAmountFieldName(): string
    {
        return self::AMOUNT_FIELD_NAME;
    }

    public function getName(): string
    {
        return $this->facade
            ->getTranslator()
            ->trans('Fixed amount discount for selected attribute values', []);
    }

    public function getToolTip(): string
    {
        return $this->facade
            ->getTranslator()
            ->trans(
                'This coupon subtracts the specified amount from the order total for each product which uses the selected attribute values. If the discount is greater than the total order, the customer will only pay the shipping, or nothing if the coupon also provides free shipping.',
                [],
            );
    }

    public function drawBackOfficeInputs(): string
    {
        return $this->callDrawBackOfficeInputs('coupon/type-fragments/remove-amount-on-attributes.html');
    }
}
