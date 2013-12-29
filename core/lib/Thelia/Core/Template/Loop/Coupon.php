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
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Model\Coupon as MCoupon;
use Thelia\Model\CouponQuery;
use Thelia\Type;

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
class Coupon extends BaseI18nLoop implements PropelSearchLoopInterface
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
            Argument::createBooleanOrBothTypeArgument('is_enabled')
        );
    }

    public function buildModelCriteria()
    {
        $search = CouponQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, array('TITLE', 'DESCRIPTION', 'SHORT_DESCRIPTION'));

        $id = $this->getId();
        $isEnabled = $this->getIsEnabled();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        if (isset($isEnabled)) {
            $search->filterByIsEnabled($isEnabled ? true : false);
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var ConditionFactory $conditionFactory */
        $conditionFactory = $this->container->get('thelia.condition.factory');

        /** @var Request $request */
        $request = $this->container->get('request');
        /** @var Lang $lang */
        $lang = $request->getSession()->getLang();

        /** @var MCoupon $coupon */
        foreach ($loopResult->getResultDataCollection() as $coupon) {
            $loopResultRow = new LoopResultRow($coupon);
            $conditions = $conditionFactory->unserializeConditionCollection(
                $coupon->getSerializedConditions()
            );

            /** @var CouponInterface $couponManager */
            $couponManager = $this->container->get($coupon->getType());
            $couponManager->set(
                $this->container->get('thelia.facade'),
                $coupon->getCode(),
                $coupon->getTitle(),
                $coupon->getShortDescription(),
                $coupon->getDescription(),
                $coupon->getEffects(),
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

            $cleanedConditions = array();
            /** @var ConditionInterface $condition */
            foreach ($conditions->getConditions() as $condition) {
                $cleanedConditions[] = $condition->getToolTip();
            }
            $loopResultRow->set("ID", $coupon->getId())
                ->set("IS_TRANSLATED", $coupon->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
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
                ->set("APPLICATION_CONDITIONS", $cleanedConditions)
                ->set("TOOLTIP", $couponManager->getToolTip())
                ->set("DAY_LEFT_BEFORE_EXPIRATION", $daysLeftBeforeExpiration)
                ->set("SERVICE_ID", $couponManager->getServiceId());
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
