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
     * The rule sets read so far, by "zone id|country id".
     *
     * Every carrier of a list asks for the rules of the zones covering the country, and a
     * checkout page builds that list several times over: the rules of a zone are read once
     * per request. Written rules drop them (AreaZipCodeRule::postSave() and postDelete()),
     * and so do a new request, a console command and a reset of the services between two
     * messages of a worker (ZipCodeRuleCacheListener). A write that skips the model hooks, a
     * bulk update or delete of this query, is only seen from the next request on.
     *
     * @var array<string, ZipCodeRuleSet>
     */
    private static array $ruleSets = [];

    /**
     * The rules of a zone that apply to the addresses of one country: the rules written for
     * that country and the rules written for every country of the zone.
     */
    public static function ruleSetFor(int $areaId, int $countryId): ZipCodeRuleSet
    {
        return self::$ruleSets[$areaId.'|'.$countryId] ??= self::readRuleSet($areaId, $countryId);
    }

    /**
     * Drops the rule sets read so far.
     *
     * @internal
     */
    public static function forgetRuleSets(): void
    {
        self::$ruleSets = [];
    }

    private static function readRuleSet(int $areaId, int $countryId): ZipCodeRuleSet
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
                // Written by hand in the database: the back office never saves one. Logged
                // once per request, the rule sets being kept for it.
                Tlog::getInstance()->warning(
                    'Postal code rule {rule_id} of zone {area_id} is ignored: "{pattern}" matches nothing.',
                    ['rule_id' => $rule->getId(), 'area_id' => $areaId, 'pattern' => $rule->getPattern()],
                );

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
