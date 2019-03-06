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

    protected static $caches = [];

    /**
     * @param TaxRule $taxRule
     * @param Country $country
     *
     * @return array|mixed|\Propel\Runtime\Collection\ObjectCollection
     */
    public function getTaxCalculatorCollection(TaxRule $taxRule, Country $country = null, State $state = null)
    {
        $key = sprintf(
            '%s-%s-%s',
            $taxRule->getId(),
            ($country !== null) ? $country->getId() : 0,
            ($state !== null) ? $state->getId() : 0
        );

        if (array_key_exists($key, self::$caches)) {
            return self::$caches[$key];
        }

        $taxRuleQuery = TaxRuleCountryQuery::create()
            ->filterByTaxRuleId($taxRule->getId());

        if (null !== $country) {
            $taxRuleQuery->filterByCountry($country, Criteria::EQUAL);
        }

        if (null !== $state) {
            $taxRuleCount = clone $taxRuleQuery;

            $taxRuleCount->filterByStateId($state->getId(), Criteria::EQUAL)
                ->count();

            if (0 === $taxRuleCount) {
                $taxRuleQuery->filterByStateId(null, Criteria::EQUAL);
            }
        }

        $search = TaxQuery::create()
            ->filterByTaxRuleCountry($taxRuleQuery->find())
            ->withColumn(TaxRuleCountryTableMap::COL_POSITION, self::ALIAS_FOR_TAX_RULE_COUNTRY_POSITION)
            ->orderBy(self::ALIAS_FOR_TAX_RULE_COUNTRY_POSITION, Criteria::ASC);
        ;

        return self::$caches[$key] = $search->find();
    }
}
// TaxRuleQuery
