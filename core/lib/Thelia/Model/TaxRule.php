<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Model;

use Thelia\Model\Base\TaxRule as BaseTaxRule;
use Thelia\TaxEngine\Calculator;
use Thelia\TaxEngine\OrderProductTaxCollection;

class TaxRule extends BaseTaxRule
{
    /**
     * @param $untaxedAmount
     * @param $untaxedPromoAmount
     * @param null $askedLocale
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
