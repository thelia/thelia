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
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\OrderCouponCountry;
use Thelia\Model\OrderCouponModule;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\OrderQuery;

/**
 *
 * OrderCoupon loop
 *
 *
 * Class OrderCoupon
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method string[] getOrder()
 */
class OrderCoupon extends BaseLoop implements PropelSearchLoopInterface
{
    /**
     * Define all args used in your loop
     *
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order', null, true)
        );
    }

    public function buildModelCriteria()
    {
        $search = OrderCouponQuery::create();

        $order = $this->getOrder();

        $search
            ->filterByOrderId($order, Criteria::EQUAL)
            ->orderById(Criteria::ASC)
        ;

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        $this->container->get('thelia.condition.factory');

        if (null !== $order = OrderQuery::create()->findPk($this->getOrder())) {
            $oneDayInSeconds = 86400;

            /** @var \Thelia\Model\OrderCoupon $orderCoupon */
            foreach ($loopResult->getResultDataCollection() as $orderCoupon) {
                $loopResultRow = new LoopResultRow($orderCoupon);

                $now = time();
                $datediff = $orderCoupon->getExpirationDate()->getTimestamp() - $now;
                $daysLeftBeforeExpiration = floor($datediff/($oneDayInSeconds));

                $freeShippingForCountriesIds = [];
                /** @var OrderCouponCountry $couponCountry */
                foreach ($orderCoupon->getFreeShippingForCountries() as $couponCountry) {
                    $freeShippingForCountriesIds[] = $couponCountry->getCountryId();
                }

                $freeShippingForModulesIds = [];
                /** @var OrderCouponModule $couponModule */
                foreach ($orderCoupon->getFreeShippingForModules() as $couponModule) {
                    $freeShippingForModulesIds[] = $couponModule->getModuleId();
                }

                $loopResultRow->set("ID", $orderCoupon->getId())
                    ->set("CODE", $orderCoupon->getCode())
                    ->set("TITLE", $orderCoupon->getTitle())
                    ->set("SHORT_DESCRIPTION", $orderCoupon->getShortDescription())
                    ->set("DESCRIPTION", $orderCoupon->getDescription())
                    ->set("EXPIRATION_DATE", $orderCoupon->getExpirationDate($order->getLangId()))
                    ->set("IS_CUMULATIVE", $orderCoupon->getIsCumulative())
                    ->set("IS_REMOVING_POSTAGE", $orderCoupon->getIsRemovingPostage())
                    ->set("IS_AVAILABLE_ON_SPECIAL_OFFERS", $orderCoupon->getIsAvailableOnSpecialOffers())
                    ->set("DAY_LEFT_BEFORE_EXPIRATION", $daysLeftBeforeExpiration)
                    ->set("FREE_SHIPPING_FOR_COUNTRIES_LIST", implode(',', $freeShippingForCountriesIds))
                    ->set("FREE_SHIPPING_FOR_MODULES_LIST", implode(',', $freeShippingForModulesIds))
                    ->set("PER_CUSTOMER_USAGE_COUNT", $orderCoupon->getPerCustomerUsageCount())
                ;
                $this->addOutputFields($loopResultRow, $orderCoupon);

                $loopResult->addRow($loopResultRow);
            }
        }

        return $loopResult;
    }
}
