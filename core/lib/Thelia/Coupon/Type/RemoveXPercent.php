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

use Thelia\Core\Translation\Translator;
use Thelia\Coupon\FacadeInterface;

/**
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RemoveXPercent extends CouponAbstract
{
    const INPUT_PERCENTAGE_NAME = 'percentage';

    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.remove_x_percent';

    /** @var float Percentage removed from the Cart */
    protected $percentage = 0;

    /**
     * @inheritdoc
     */
    public function set(
        FacadeInterface $facade,
        $code,
        $title,
        $shortDescription,
        $description,
        array $effects,
        $isCumulative,
        $isRemovingPostage,
        $isAvailableOnSpecialOffers,
        $isEnabled,
        $maxUsage,
        \DateTime $expirationDate,
        $freeShippingForCountries,
        $freeShippingForModules,
        $perCustomerUsageCount
    )
    {
        parent::set(
            $facade, $code, $title, $shortDescription, $description, $effects,
            $isCumulative, $isRemovingPostage, $isAvailableOnSpecialOffers, $isEnabled, $maxUsage, $expirationDate,
            $freeShippingForCountries,
            $freeShippingForModules,
            $perCustomerUsageCount
        );

        $this->percentage = $effects[self::INPUT_PERCENTAGE_NAME];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function exec()
    {
       return round($this->facade->getCartTotalTaxPrice($this->isAvailableOnSpecialOffers()) *  $this->percentage/100, 2);
    }

    /**
     * @inheritdoc
     */
    protected function checkCouponFieldValue($fieldName, $fieldValue)
    {
        if ($fieldName === self::INPUT_PERCENTAGE_NAME) {

            $pcent = floatval($fieldValue);

            if ($pcent <= 0 || $pcent > 100) {
                throw new \InvalidArgumentException(
                    Translator::getInstance()->trans(
                        'Value %val for Percent Discount is invalid. Please enter a positive value between 1 and 100.',
                        [ '%val' => $fieldValue]
                    )
                );
            }
        }

        return $fieldValue;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Remove X percent to total cart', array(), 'coupon');
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
                array(),
                'coupon'
            );

        return $toolTip;
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        return $this->facade->getParser()->render('coupon/type-fragments/remove-x-percent.html', [
            'fieldName' => $this->makeCouponFieldName(self::INPUT_PERCENTAGE_NAME),
            'value'     => $this->percentage
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function getFieldList()
    {
        return [self::INPUT_PERCENTAGE_NAME];
    }
}
