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
 * Allow to remove an amount from the checkout total
 *
 * @package Coupon
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class RemoveAmountOnAttributeValues extends AbstractRemoveOnAttributeValues
{
    use AmountCouponTrait;

    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.remove_amount_on_attribute_av';

    protected function getAmountFieldName()
    {
        return self::AMOUNT_FIELD_NAME;
    }
    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Fixed amount discount for selected attribute values', array());
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->facade
            ->getTranslator()
            ->trans(
                'This coupon subtracts the specified amount from the order total for each product which uses the selected attribute values. If the discount is greater than the total order, the customer will only pay the shipping, or nothing if the coupon also provides free shipping.',
                array()
            );

        return $toolTip;
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        return $this->callDrawBackOfficeInputs('coupon/type-fragments/remove-amount-on-attributes.html');
    }
}
