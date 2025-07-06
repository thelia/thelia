<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Module;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Model\TaxRuleQuery;
use Thelia\TaxEngine\Calculator;

abstract class AbstractDeliveryModuleWithState extends BaseModule implements DeliveryModuleWithStateInterface
{
    // This class is the base class for delivery modules
    // It may contains common methods in the future.

    /**
     * @return bool
     */
    public function handleVirtualProductDelivery()
    {
        return false;
    }

    /**
     * Return the first area that matches the given  country for the given module.
     *
     * @return Area|null
     */
    public function getAreaForCountry(Country $country, ?State $state = null)
    {
        $area = null;

        if (null !== $areaDeliveryModule = AreaDeliveryModuleQuery::create()->findByCountryAndModule(
            $country,
            $this->getModuleModel(),
            $state
        )) {
            $area = $areaDeliveryModule->getArea();
        }

        return $area;
    }

    public function getDeliveryMode()
    {
        return 'delivery';
    }

    public function buildOrderPostage($untaxedPostage, Country $country, $locale, $taxRuleId = null)
    {
        $taxRuleQuery = TaxRuleQuery::create();
        $taxRuleId = ($taxRuleId) ?: ConfigQuery::read('taxrule_id_delivery_module');
        if ($taxRuleId) {
            $taxRuleQuery->filterById($taxRuleId);
        }

        $taxRule = $taxRuleQuery->orderByIsDefault(Criteria::DESC)->findOne();

        $orderPostage = new OrderPostage();
        $taxCalculator = new Calculator();
        $taxCalculator->loadTaxRuleWithoutProduct($taxRule, $country);

        $orderPostage->setAmount($taxCalculator->getTaxedPrice($untaxedPostage));
        $orderPostage->setAmountTax($taxCalculator->getTaxAmountFromUntaxedPrice($untaxedPostage));
        $orderPostage->setTaxRuleTitle($taxRule->setLocale($locale)->getTitle());

        return $orderPostage;
    }
}
