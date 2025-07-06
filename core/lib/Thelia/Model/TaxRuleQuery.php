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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Base\TaxRuleQuery as BaseTaxRuleQuery;
use Thelia\Model\Map\TaxRuleCountryTableMap;

/**
 * Skeleton subclass for performing query and update operations on the 'tax_rule' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class TaxRuleQuery extends BaseTaxRuleQuery
{
    public const ALIAS_FOR_TAX_RULE_COUNTRY_POSITION = 'taxRuleCountryPosition';

    protected static $caches = [];

    /**
     * @throws PropelException
     *
     * @return array|mixed|ActiveRecordInterface[]|ObjectCollection|Tax[]
     */
    public function getTaxCalculatorCollection(TaxRule $taxRule, ?Country $country = null, ?State $state = null)
    {
        $key = \sprintf(
            '%s-%s-%s',
            $taxRule->getId(),
            ($country instanceof Country) ? $country->getId() : 0,
            ($state instanceof State) ? $state->getId() : 0
        );

        if (\array_key_exists($key, self::$caches)) {
            return self::$caches[$key];
        }

        $taxRuleQuery = TaxRuleCountryQuery::create()
            ->filterByTaxRuleId($taxRule->getId());

        if ($country instanceof Country) {
            $taxRuleQuery->filterByCountry($country, Criteria::EQUAL);
        }

        $taxRuleQuery->groupByTaxId();

        $synthetizedSateId = $state;

        if ($state instanceof State) {
            $taxRuleCount = clone $taxRuleQuery;

            if (0 === $taxRuleCount->filterByStateId($state->getId(), Criteria::EQUAL)->count()) {
                $synthetizedSateId = null;
            }
        }

        $taxRuleQuery->filterByStateId($synthetizedSateId, Criteria::EQUAL);

        $search = TaxQuery::create()
            ->filterByTaxRuleCountry($taxRuleQuery->find())
            ->withColumn(TaxRuleCountryTableMap::COL_POSITION, self::ALIAS_FOR_TAX_RULE_COUNTRY_POSITION)
            ->orderBy(self::ALIAS_FOR_TAX_RULE_COUNTRY_POSITION, Criteria::ASC);

        return self::$caches[$key] = $search->find();
    }
}

// TaxRuleQuery
