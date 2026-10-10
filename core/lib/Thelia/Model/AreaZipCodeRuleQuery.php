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
use Thelia\Domain\Shipping\ZipCode\ZipCodePattern;
use Thelia\Domain\Shipping\ZipCode\ZipCodeRuleSet;
use Thelia\Log\Tlog;
use Thelia\Model\Base\AreaZipCodeRuleQuery as BaseAreaZipCodeRuleQuery;
use Thelia\Model\Map\AreaZipCodeRuleTableMap;

class AreaZipCodeRuleQuery extends BaseAreaZipCodeRuleQuery
{
    /**
     * The rules of a zone that apply to the addresses of one country: the rules written for
     * that country and the rules written for every country of the zone.
     */
    public static function ruleSetFor(int $areaId, int $countryId): ZipCodeRuleSet
    {
        $rules = self::create()
            ->filterByAreaId($areaId)
            ->condition('anyCountry', AreaZipCodeRuleTableMap::COL_COUNTRY_ID.' IS NULL')
            ->condition('thisCountry', AreaZipCodeRuleTableMap::COL_COUNTRY_ID.' = ?', $countryId)
            ->where(['anyCountry', 'thisCountry'], Criteria::LOGICAL_OR)
            ->find();

        $inclusions = [];
        $exclusions = [];

        foreach ($rules as $rule) {
            try {
                $pattern = ZipCodePattern::fromString((string) $rule->getPattern());
            } catch (\InvalidArgumentException) {
                // Written by hand in the database: the back office never saves one.
                Tlog::getInstance()->warning(\sprintf('Postal code rule %d of zone %d is ignored: "%s" matches nothing.', $rule->getId(), $areaId, $rule->getPattern()));

                continue;
            }

            if ($rule->getExcluded()) {
                $exclusions[] = $pattern;
            } else {
                $inclusions[] = $pattern;
            }
        }

        return new ZipCodeRuleSet($inclusions, $exclusions);
    }
}
