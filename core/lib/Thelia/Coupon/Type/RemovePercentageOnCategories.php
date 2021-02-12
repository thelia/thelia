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
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class RemovePercentageOnCategories extends AbstractRemoveOnCategories
{
    public const PERCENTAGE = 'percentage';

    use PercentageCouponTrait;

    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.remove_percentage_on_categories';

    /**
     * @inheritdoc
     */
    protected function getPercentageFieldName()
    {
        return self::PERCENTAGE;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Percentage discount for selected categories', []);
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->facade
            ->getTranslator()
            ->trans(
                'This coupon subtracts from the order total a percentage of the price of each product which belongs to the selected categories. If the discount is greater than the total order, the customer will only pay the shipping, or nothing if the coupon also provides free shipping.',
                []
            );

        return $toolTip;
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        return $this->callDrawBackOfficeInputs('coupon/type-fragments/remove-percentage-on-categories.html');
    }
}
