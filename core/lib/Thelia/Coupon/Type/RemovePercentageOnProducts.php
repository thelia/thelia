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

use Thelia\Model\Product;

/**
 * Allow to remove an amount from the checkout total
 *
 * @package Coupon
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class RemovePercentageOnProducts extends AbstractRemoveOnProducts
{
    const PERCENTAGE = 'percentage';

    use PercentageCouponTrait;

    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.remove_percentage_on_products';

    /**
     * @inheritdoc
     */
    protected function getPercentageFieldName()
    {
        return self::PERCENTAGE;
    }

    /**
     * Get I18n name
     *
     * @return string
     */
    public function getName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Percentage discount for selected products', array());
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->facade
            ->getTranslator()
            ->trans(
                'This coupon subtracts from the order total the specified percentage of each selected product price. If the discount is greater than the total order, the customer will only pay the shipping, or nothing if the coupon also provides free shipping.',
                array()
            );

        return $toolTip;
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        return $this->callDrawBackOfficeInputs('coupon/type-fragments/remove-percentage-on-products.html');
    }
}
