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

namespace Thelia\Domain\Shipping\ZipCode;

use Thelia\Model\AreaQuery;
use Thelia\Model\AreaZipCodeRule;
use Thelia\Model\AreaZipCodeRuleQuery;
use Thelia\Model\CountryAreaQuery;

/**
 * Writes the postal code rules of a delivery zone, refusing what could never match: a
 * pattern that matches nothing, or a country the zone does not hold.
 */
final readonly class AreaZipCodeRuleWriter
{
    /**
     * @throws \InvalidArgumentException with a sentence the back office shows as is
     */
    public function add(int $areaId, string $pattern, bool $excluded, ?int $countryId): AreaZipCodeRule
    {
        if (null === AreaQuery::create()->findPk($areaId)) {
            throw new \InvalidArgumentException('This delivery zone does not exist.');
        }

        $normalized = ZipCodePattern::fromString($pattern);

        if (null !== $countryId && !CountryAreaQuery::create()->filterByAreaId($areaId)->filterByCountryId($countryId)->exists()) {
            throw new \InvalidArgumentException('A rule can only name a country of its zone.');
        }

        $rule = (new AreaZipCodeRule())
            ->setAreaId($areaId)
            ->setCountryId($countryId)
            ->setPattern((string) $normalized)
            ->setExcluded($excluded);
        $rule->save();

        return $rule;
    }

    /**
     * Removes a rule of the zone; a rule of another zone is left alone.
     */
    public function remove(int $areaId, int $ruleId): bool
    {
        $rule = AreaZipCodeRuleQuery::create()->filterByAreaId($areaId)->findPk($ruleId);

        if (null === $rule) {
            return false;
        }

        $rule->delete();

        return true;
    }
}
