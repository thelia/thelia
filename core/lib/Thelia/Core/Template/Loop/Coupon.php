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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Util\PropelModelPager;
use Thelia\Constraint\ConstraintFactory;
use Thelia\Constraint\Rule\CouponRuleInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Coupon\Type\CouponInterface;
use Thelia\Model\CouponQuery;
use Thelia\Model\Coupon as MCoupon;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Coupon Loop
 *
 * @package Thelia\Core\Template\Loop
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class Coupon extends BaseI18nLoop
{
    /**
     * Define all args used in your loop
     *
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createBooleanOrBothTypeArgument('is_enabled', 1)
        );
    }

    /**
     * Execute Loop
     *
     * @param PropelModelPager $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = CouponQuery::create();

        /* manage translations */
        $locale = $this->configureI18nProcessing($search, array('TITLE', 'DESCRIPTION', 'SHORT_DESCRIPTION'));

        $id = $this->getId();
        $isEnabled = $this->getIsEnabled();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        if ($isEnabled != BooleanOrBothType::ANY) {
            $search->filterByIsEnabled($isEnabled ? 1 : 0);
        }

        // Perform search
        $coupons = $this->search($search, $pagination);

        $loopResult = new LoopResult();
        /** @var ConstraintFactory $constraintFactory */
        $constraintFactory = $this->container->get('thelia.constraint.factory');

        /** @var Request $request */
        $request = $this->container->get('request');
        /** @var Lang $lang */
        $lang = $request->getSession()->getLang();

        /** @var MCoupon $coupon */
        foreach ($coupons as $coupon) {
            $loopResultRow = new LoopResultRow();
            $rules = $constraintFactory->unserializeCouponRuleCollection(
                $coupon->getSerializedRules()
            );

            /** @var CouponInterface $couponManager */
            $couponManager = $this->container->get($coupon->getType());
            $couponManager->set(
                $this->container->get('thelia.adapter'),
                $coupon->getCode(),
                $coupon->getTitle(),
                $coupon->getShortDescription(),
                $coupon->getDescription(),
                $coupon->getAmount(),
                $coupon->getIsCumulative(),
                $coupon->getIsRemovingPostage(),
                $coupon->getIsAvailableOnSpecialOffers(),
                $coupon->getIsEnabled(),
                $coupon->getMaxUsage(),
                $coupon->getExpirationDate()
            );

            $now = time();
            $datediff = $coupon->getExpirationDate()->getTimestamp() - $now;
            $daysLeftBeforeExpiration = floor($datediff/(60*60*24));

            $cleanedRules = array();
            /** @var CouponRuleInterface $rule */
            foreach ($rules->getRules() as $rule) {
                $cleanedRules[] = $rule->getToolTip();
            }
            $loopResultRow->set("ID", $coupon->getId())
                ->set("IS_TRANSLATED", $coupon->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $locale)
                ->set("CODE", $coupon->getCode())
                ->set("TITLE", $coupon->getVirtualColumn('i18n_TITLE'))
                ->set("SHORT_DESCRIPTION", $coupon->getVirtualColumn('i18n_SHORT_DESCRIPTION'))
                ->set("DESCRIPTION", $coupon->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("EXPIRATION_DATE", $coupon->getExpirationDate($lang->getDateFormat()))
                ->set("USAGE_LEFT", $coupon->getMaxUsage())
                ->set("IS_CUMULATIVE", $coupon->getIsCumulative())
                ->set("IS_REMOVING_POSTAGE", $coupon->getIsRemovingPostage())
                ->set("IS_AVAILABLE_ON_SPECIAL_OFFERS", $coupon->getIsAvailableOnSpecialOffers())
                ->set("IS_ENABLED", $coupon->getIsEnabled())
                ->set("AMOUNT", $coupon->getAmount())
                ->set("APPLICATION_CONDITIONS", $cleanedRules)
                ->set("TOOLTIP", $couponManager->getToolTip())
                ->set("DAY_LEFT_BEFORE_EXPIRATION", $daysLeftBeforeExpiration)
                ->set("SERVICE_ID", $couponManager->getServiceId());
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
