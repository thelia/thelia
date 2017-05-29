<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Coupon\Type;

/**
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>, Franck Allimant <franck@cqfdev.fr>
 *
 */
class RemoveXPercent extends AbstractRemove
{
    const INPUT_PERCENTAGE_NAME = 'percentage';

    use PercentageCouponTrait;

    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.remove_x_percent';

    /**
     * @inheritdoc
     */
    protected function getPercentageFieldName()
    {
        return self::INPUT_PERCENTAGE_NAME;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Remove X percent to total cart', array());
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->facade
            ->getTranslator()
            ->trans(
                'This coupon will offert a flat percentage off a shopper\'s entire order (not applied to shipping costs or tax rates). If the discount is greater than the total order corst, the customer will only pay the shipping, or nothing if the coupon also provides free shipping.',
                array()
            );

        return $toolTip;
    }

    /**
     * @inheritdoc
     */
    public function exec()
    {
        return ($this->facade->getCartTotalTaxPrice($this->isAvailableOnSpecialOffers()) *  $this->percentage/100);
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        return $this->callDrawBackOfficeInputs('coupon/type-fragments/remove-x-percent.html');
    }
}
