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

/**
 * @author  Guillaume MOREL <gmorel@openstudio.fr>, Franck Allimant <franck@cqfdev.fr>
 */
class RemoveXPercent extends AbstractRemove
{
    use PercentageCouponTrait;

    public const INPUT_PERCENTAGE_NAME = 'percentage';

    protected string $serviceId = 'thelia.coupon.type.remove_x_percent';

    protected function getPercentageFieldName(): string
    {
        return self::INPUT_PERCENTAGE_NAME;
    }

    public function getName(): string
    {
        return $this->facade
            ->getTranslator()
            ->trans('Remove X percent to total cart', []);
    }

    public function getToolTip(): string
    {
        return $this->facade
            ->getTranslator()
            ->trans(
                "This coupon will offert a flat percentage off a shopper's entire order (not applied to shipping costs or tax rates). If the discount is greater than the total order corst, the customer will only pay the shipping, or nothing if the coupon also provides free shipping.",
                [],
            );
    }

    public function exec(): float
    {
        return $this->facade->getCartTotalTaxPrice($this->isAvailableOnSpecialOffers()) * $this->percentage / 100;
    }

    public function drawBackOfficeInputs(): string
    {
        return $this->callDrawBackOfficeInputs('coupon/type-fragments/remove-x-percent.html');
    }
}
