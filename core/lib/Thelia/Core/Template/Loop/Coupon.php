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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Model\CouponModule;
use Thelia\Model\Coupon as MCoupon;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponQuery;
use Thelia\Model\Map\CouponTableMap;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Coupon Loop
 *
 * @package Thelia\Core\Template\Loop
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method bool|string  getIsEnabled()
 * @method bool getInUse()
 * @method string[] getOrder()
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
            Argument::createBooleanOrBothTypeArgument('is_enabled'),
            Argument::createBooleanTypeArgument('in_use'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        array(
                        'id', 'id-reverse',
                        'code', 'code-reverse',
                        'title', 'title-reverse',
                        'enabled', 'enabled-reverse',
                        'expiration-date', 'expiration-date-reverse',
                        'days-left', 'days-left-reverse',
                        'usages-left', 'usages-left-reverse'
                        )
                    )
                ),
                'code'
            )
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

        $inUse = $this->getInUse();

        if ($inUse !== null) {
            // Get the code of coupons currently in use
            $consumedCoupons = $this->request->getSession()->getConsumedCoupons();

            // Get only matching coupons.
            $criteria = $inUse ? Criteria::IN : Criteria::NOT_IN;

            $search->filterByCode($consumedCoupons, $criteria);
        }

        $search->addAsColumn('days_left', 'DATEDIFF('.CouponTableMap::EXPIRATION_DATE.', CURDATE()) - 1');

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id-reverse':
                    $search->orderById(Criteria::DESC);
                    break;

                case 'code':
                    $search->orderByCode(Criteria::ASC);
                    break;
                case 'code-reverse':
                    $search->orderByCode(Criteria::DESC);
                    break;

                case 'title':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'title-reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;

                case 'enabled':
                    $search->orderByIsEnabled(Criteria::ASC);
                    break;
                case 'enabled-reverse':
                    $search->orderByIsEnabled(Criteria::DESC);
                    break;

                case 'expiration-date':
                    $search->orderByExpirationDate(Criteria::ASC);
                    break;
                case 'expiration-date-reverse':
                    $search->orderByExpirationDate(Criteria::DESC);
                    break;

                case 'usages-left':
                    $search->orderByMaxUsage(Criteria::ASC);
                    break;
                case 'usages-left-reverse':
                    $search->orderByMaxUsage(Criteria::DESC);
                    break;

                case 'days-left':
                    $search->addAscendingOrderByColumn('days_left');
                    break;
                case 'days-left-reverse':
                    $search->addDescendingOrderByColumn('days_left');
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var ConditionFactory $conditionFactory */
        $conditionFactory = $this->container->get('thelia.condition.factory');

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
                $coupon->getExpirationDate(),
                $coupon->getFreeShippingForCountries(),
                $coupon->getFreeShippingForModules(),
                $coupon->getPerCustomerUsageCount()
            );

            $cleanedConditions = array();
            /** @var ConditionInterface $condition */
            foreach ($conditions as $condition) {
                $temp = array(
                    'toolTip' => $condition->getToolTip(),
                    'summary' => $condition->getSummary()
                );
                $cleanedConditions[] = $temp;
            }

            $freeShippingForCountriesIds = [];
            /** @var CouponCountry $couponCountry */
            foreach ($coupon->getFreeShippingForCountries() as $couponCountry) {
                $freeShippingForCountriesIds[] = $couponCountry->getCountryId();
            }

            $freeShippingForModulesIds = [];
            /** @var CouponModule $couponModule */
            foreach ($coupon->getFreeShippingForModules() as $couponModule) {
                $freeShippingForModulesIds[] = $couponModule->getModuleId();
            }

            // If and only if the coupon is currently in use, get the coupon discount. Calling exec() on a coupon
            // which is not currently in use may apply coupon on the cart. This is true for coupons such as FreeProduct,
            // which adds a product to the cart.
            $discount = $couponManager->isInUse() ? $couponManager->exec() : 0;

            $loopResultRow
                ->set("ID", $coupon->getId())
                ->set("IS_TRANSLATED", $coupon->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
                ->set("CODE", $coupon->getCode())
                ->set("TITLE", $coupon->getVirtualColumn('i18n_TITLE'))
                ->set("SHORT_DESCRIPTION", $coupon->getVirtualColumn('i18n_SHORT_DESCRIPTION'))
                ->set("DESCRIPTION", $coupon->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("EXPIRATION_DATE", $coupon->getExpirationDate())
                ->set("USAGE_LEFT", $coupon->getMaxUsage())
                ->set("PER_CUSTOMER_USAGE_COUNT", $coupon->getPerCustomerUsageCount())
                ->set("IS_CUMULATIVE", $coupon->getIsCumulative())
                ->set("IS_REMOVING_POSTAGE", $coupon->getIsRemovingPostage())
                ->set("IS_AVAILABLE_ON_SPECIAL_OFFERS", $coupon->getIsAvailableOnSpecialOffers())
                ->set("IS_ENABLED", $coupon->getIsEnabled())
                ->set("AMOUNT", $coupon->getAmount())
                ->set("APPLICATION_CONDITIONS", $cleanedConditions)
                ->set("TOOLTIP", $couponManager->getToolTip())
                ->set("DAY_LEFT_BEFORE_EXPIRATION", max(0, $coupon->getVirtualColumn('days_left')))
                ->set("SERVICE_ID", $couponManager->getServiceId())
                ->set("FREE_SHIPPING_FOR_COUNTRIES_LIST", implode(',', $freeShippingForCountriesIds))
                ->set("FREE_SHIPPING_FOR_MODULES_LIST", implode(',', $freeShippingForModulesIds))
                ->set("DISCOUNT_AMOUNT", $discount)
            ;
            $this->addOutputFields($loopResultRow, $coupon);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
