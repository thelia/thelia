<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\TaxRuleQuery as BaseTaxRuleQuery;
use Thelia\Model\Map\TaxRuleCountryTableMap;

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
    const ALIAS_FOR_TAX_RULE_COUNTRY_POSITION = 'taxRuleCountryPosition';

    /**
     * @param TaxRule $taxRule
     * @param Country $country
     *
     * @return array|mixed|\Propel\Runtime\Collection\ObjectCollection
     */
    public function getTaxCalculatorCollection(TaxRule $taxRule, Country $country)
    {
        $search = TaxQuery::create()
            ->filterByTaxRuleCountry(
                TaxRuleCountryQuery::create()
                    ->filterByCountry($country, Criteria::EQUAL)
                    ->filterByTaxRuleId($taxRule->getId())
                    ->orderByPosition()
                    ->find()
            )
            ->withColumn(TaxRuleCountryTableMap::POSITION, self::ALIAS_FOR_TAX_RULE_COUNTRY_POSITION)
            ->orderBy(self::ALIAS_FOR_TAX_RULE_COUNTRY_POSITION, Criteria::ASC);
        ;

        return $search->find();
    }
}
// TaxRuleQuery
