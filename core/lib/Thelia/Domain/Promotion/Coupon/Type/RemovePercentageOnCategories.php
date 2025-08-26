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
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class RemovePercentageOnCategories extends AbstractRemoveOnCategories
{
    use PercentageCouponTrait;

    public const PERCENTAGE = 'percentage';

    protected string $serviceId = 'thelia.coupon.type.remove_percentage_on_categories';

    protected function getPercentageFieldName(): string
    {
        return self::PERCENTAGE;
    }

    public function getName(): string
    {
        return $this->facade
            ->getTranslator()
            ->trans('Percentage discount for selected categories', []);
    }

    public function getToolTip(): string
    {
        return $this->facade
            ->getTranslator()
            ->trans(
                'This coupon subtracts from the order total a percentage of the price of each product which belongs to the selected categories. If the discount is greater than the total order, the customer will only pay the shipping, or nothing if the coupon also provides free shipping.',
                [],
            );
    }

    public function drawBackOfficeInputs(): string
    {
        return $this->callDrawBackOfficeInputs('coupon/type-fragments/remove-percentage-on-categories.html');
    }
}
