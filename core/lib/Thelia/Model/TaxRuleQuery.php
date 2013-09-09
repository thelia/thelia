<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\TaxRuleQuery as BaseTaxRuleQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'tax_rule' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class TaxRuleQuery extends BaseTaxRuleQuery
{
    public function getTaxCalculatorCollection(Product $product, Country $country)
    {
        $search = TaxRuleCountryQuery::create()
            ->filterByCountry($country, Criteria::EQUAL)
            ->filterByTaxRuleId($product->getTaxRuleId())
            ->orderByPosition()
            ->find();

        return $search;
    }
} // TaxRuleQuery
