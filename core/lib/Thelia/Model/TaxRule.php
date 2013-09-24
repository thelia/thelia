<?php

namespace Thelia\Model;

use Thelia\Model\Base\TaxRule as BaseTaxRule;
use Thelia\TaxEngine\Calculator;
use Thelia\TaxEngine\OrderProductTaxCollection;

class TaxRule extends BaseTaxRule
{
    /**
     * @param Country $country
     * @param         $untaxedAmount
     * @param null    $askedLocale
     *
     * @return OrderProductTaxCollection
     */
    public function getTaxDetail(Country $country, $untaxedAmount, $askedLocale = null)
    {
        $taxCalculator = new Calculator();

        $taxCollection = new OrderProductTaxCollection();
        $taxCalculator->loadTaxRule($this, $country)->getTaxedPrice($untaxedAmount, $taxCollection, $askedLocale);

        return $taxCollection;
    }
}
