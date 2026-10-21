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
     * messages of a worker (ZipCodeRuleCacheListener).
     *
     * What the model hooks do not see is only read again from the next request, command or
     * message on: a bulk update or delete of this query; the rows the database deletes in
     * cascade with their zone or their country; SQL written straight to the table, by this
     * process or another one; a rule saved, read, then rolled back with its transaction, which
     * stays read as saved; and, during a long console command, any rule another process writes.
     *
     * @var array<string, ZipCodeRuleSet>
     */
    private static array $ruleSets = [];

    /**
     * The rules of a zone that apply to the addresses of one country: the rules written for
     * that country and the rules written for every country of the zone.
     *
     * A row whose pattern cannot be read (empty, only *, a range upside down or of two
     * lengths, too long: the back office refuses all of them, so it was written by another
     * way) matches no postal code, and is logged once per zone and country a request reads.
     * As an exclusion it then excludes nothing: it says nothing the shop could honour, and
     * refusing every code of the country for it would take the carriers of the zone away
     * from the whole country because of one unreadable row. As an inclusion it takes
     * nothing, and the zone keeps its other inclusions only: an unreadable inclusion must
     * not open the zone to every code. A pattern that can be read but that PCRE gives up on
     * for one code is another case (ZipCodePattern::matches()): its intent is known, only
     * that code is uncertain, and an exclusion refuses it.
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
            $excluded = (bool) $rule->getExcluded();

            try {
                $pattern = ZipCodePattern::fromString((string) $rule->getPattern());
            } catch (\InvalidArgumentException $exception) {
                // Written by hand in the database: the back office never saves one. Logged
                // once per zone and country a request reads, the rule sets being kept for it.
                Tlog::getInstance()->warning(
                    'Postal code rule {rule_id} of zone {area_id} cannot be read ("{pattern}": {reason}): {consequence}',
                    [
                        'rule_id' => $rule->getId(),
                        'area_id' => $areaId,
                        'pattern' => $rule->getPattern(),
                        'reason' => $exception->getMessage(),
                        'consequence' => $excluded ? 'it excludes no postal code.' : 'it takes no postal code, and the zone keeps its other inclusions only.',
                    ],
                );

                $pattern = ZipCodePattern::matchingNothing();
            }

            if ($excluded) {
                $exclusions[] = $pattern;
            } else {
                $inclusions[] = $pattern;
            }
        }

        return new ZipCodeRuleSet($inclusions, $exclusions);
    }
}
