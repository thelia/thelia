<?php

namespace Thelia\Model;

use Thelia\Model\Base\TaxRule as BaseTaxRule;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\TaxEngine\Calculator;
use Thelia\TaxEngine\OrderProductTaxCollection;

class TaxRule extends BaseTaxRule
{
    use ModelEventDispatcherTrait;

    /**
     * @param Product $product
     * @param Country $country
     * @param $untaxedAmount
     * @param $untaxedPromoAmount
     * @param null    $askedLocale
     *
     * @return OrderProductTaxCollection
     */
    public function getTaxDetail(Product $product, Country $country, $untaxedAmount, $untaxedPromoAmount, $askedLocale = null)
    {
        $taxCalculator = new Calculator();

        $taxCollection = new OrderProductTaxCollection();
        $taxCalculator->loadTaxRule($this, $country, $product)->getTaxedPrice($untaxedAmount, $taxCollection, $askedLocale);
        $promoTaxCollection = new OrderProductTaxCollection();
        $taxCalculator->loadTaxRule($this, $country, $product)->getTaxedPrice($untaxedPromoAmount, $promoTaxCollection, $askedLocale);

        foreach ($taxCollection as $index => $tax) {
            $tax->setPromoAmount($promoTaxCollection->getKey($index)->getAmount());
        }

        return $taxCollection;
    }
}
