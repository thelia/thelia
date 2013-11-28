<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Coupon\Type;

use Thelia\Coupon\FacadeInterface;
use Thelia\Coupon\Type\CouponAbstract;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Allow to remove an amount from the checkout total
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RemoveXAmount extends CouponAbstract
{
    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.remove_x_amount';

    /**
     * Set Coupon
     *
     * @param FacadeInterface $facade                     Provides necessary value from Thelia
     * @param string          $code                       Coupon code (ex: XMAS)
     * @param string          $title                      Coupon title (ex: Coupon for XMAS)
     * @param string          $shortDescription           Coupon short description
     * @param string          $description                Coupon description
     * @param float           $amount                     Coupon amount to deduce
     * @param bool            $isCumulative               If Coupon is cumulative
     * @param bool            $isRemovingPostage          If Coupon is removing postage
     * @param bool            $isAvailableOnSpecialOffers If available on Product already
     *                                                    on special offer price
     * @param bool            $isEnabled                  False if Coupon is disabled by admin
     * @param int             $maxUsage                   How many usage left
     * @param \Datetime       $expirationDate             When the Code is expiring
     */
    public function set(
        FacadeInterface $facade,
        $code,
        $title,
        $shortDescription,
        $description,
        $amount,
        $isCumulative,
        $isRemovingPostage,
        $isAvailableOnSpecialOffers,
        $isEnabled,
        $maxUsage,
        \DateTime $expirationDate
    )
    {
        $this->code = $code;
        $this->title = $title;
        $this->shortDescription = $shortDescription;
        $this->description = $description;

        $this->isCumulative = $isCumulative;
        $this->isRemovingPostage = $isRemovingPostage;

        $this->amount = $amount;

        $this->isAvailableOnSpecialOffers = $isAvailableOnSpecialOffers;
        $this->isEnabled = $isEnabled;
        $this->maxUsage = $maxUsage;
        $this->expirationDate = $expirationDate;
        $this->facade = $facade;
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
            ->trans('Remove X amount to total cart', array(), 'constraint');
    }

    /**
     * Get I18n tooltip
     *
     * @return string
     */
    public function getToolTip()
    {
        $toolTip = $this->facade
            ->getTranslator()
            ->trans(
                'This coupon will remove the entered amount to the customer total checkout. If the discount is superior to the total checkout price the customer will only pay the postage. Unless if the coupon is set to remove postage too.',
                array(),
                'constraint'
            );

        return $toolTip;
    }
}
