<?php

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
class RemovePercentageOnAttributeValues extends AbstractRemoveOnAttributeValues
{
    use PercentageCouponTrait;
    public const PERCENTAGE = 'percentage';

    /** @var string Service Id */
    protected $serviceId = 'thelia.coupon.type.remove_percentage_on_attribute_av';

    protected function getPercentageFieldName()
    {
        return self::PERCENTAGE;
    }

    public function getName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Percentage discount for selected attribute values', []);
    }

    public function getToolTip()
    {
        $toolTip = $this->facade
            ->getTranslator()
            ->trans(
                'This coupon subtracts from the order total the specified percentage of each product price which uses the selected attribute values. If the discount is greater than the total order, the customer will only pay the shipping, or nothing if the coupon also provides free shipping.',
                []
            );

        return $toolTip;
    }

    public function drawBackOfficeInputs()
    {
        return $this->callDrawBackOfficeInputs('coupon/type-fragments/remove-percentage-on-attributes.html');
    }
}
