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

namespace Thelia\Domain\Shipping\DestinationRestriction;

use Thelia\Model\CountryI18nQuery;
use Thelia\Model\DestinationRestriction;
use Thelia\Model\DestinationRestrictionCountryQuery;
use Thelia\Model\DestinationRestrictionI18nQuery;
use Thelia\Model\Map\CountryI18nTableMap;
use Thelia\Model\Map\DestinationRestrictionCountryTableMap;
use Thelia\Model\Map\DestinationRestrictionI18nTableMap;
use Thelia\Model\Map\DestinationRestrictionTableMap;
use Thelia\Model\Map\ProductDestinationRestrictionTableMap;
use Thelia\Model\Map\StateI18nTableMap;
use Thelia\Model\ProductDestinationRestrictionQuery;
use Thelia\Model\StateI18nQuery;

/**
 * What the page of one product says about where it ships: for each active group of the
 * product, whether it lists the destinations refused or the only ones served, the
 * reason of the group, and the destinations by name in the visitor's language.
 *
 * Read for the product page the visitor is on, never for a list: the restrictions of
 * a whole catalogue are not served in one go.
 */
final readonly class ProductDestinationRestrictionReader
{
    /**
     * @return list<array{mode: int, onlyServed: bool, reason: string, destinations: list<string>, countryIds: list<int>}>
     */
    public function forProduct(int $productId, string $locale): array
    {
        $modeOfGroup = [];

        foreach (ProductDestinationRestrictionQuery::create()
            ->filterByProductId($productId)
            ->useDestinationRestrictionQuery()
                ->filterByActive(1)
                ->orderByPosition()
            ->endUse()
            ->select([ProductDestinationRestrictionTableMap::COL_DESTINATION_RESTRICTION_ID, DestinationRestrictionTableMap::COL_MODE])
            ->find() as $row) {
            $modeOfGroup[(int) $row[ProductDestinationRestrictionTableMap::COL_DESTINATION_RESTRICTION_ID]] = (int) $row[DestinationRestrictionTableMap::COL_MODE];
        }

        if ([] === $modeOfGroup) {
            return [];
        }

        $destinationsOfGroup = [];
        $countryIds = [];
        $stateIds = [];

        foreach (DestinationRestrictionCountryQuery::create()
            ->filterByDestinationRestrictionId(array_keys($modeOfGroup))
            ->orderById()
            ->select([DestinationRestrictionCountryTableMap::COL_DESTINATION_RESTRICTION_ID, DestinationRestrictionCountryTableMap::COL_COUNTRY_ID, DestinationRestrictionCountryTableMap::COL_STATE_ID])
            ->find() as $row) {
            $countryId = (int) $row[DestinationRestrictionCountryTableMap::COL_COUNTRY_ID];
            $stateId = null !== $row[DestinationRestrictionCountryTableMap::COL_STATE_ID] ? (int) $row[DestinationRestrictionCountryTableMap::COL_STATE_ID] : null;
            $destinationsOfGroup[(int) $row[DestinationRestrictionCountryTableMap::COL_DESTINATION_RESTRICTION_ID]][] = [$countryId, $stateId];
            $countryIds[$countryId] = true;

            if (null !== $stateId) {
                $stateIds[$stateId] = true;
            }
        }

        $countryNames = $this->namesOf(CountryI18nQuery::create(), CountryI18nTableMap::COL_ID, CountryI18nTableMap::COL_TITLE, array_keys($countryIds), $locale);
        $stateNames = [] !== $stateIds ? $this->namesOf(StateI18nQuery::create(), StateI18nTableMap::COL_ID, StateI18nTableMap::COL_TITLE, array_keys($stateIds), $locale) : [];
        $reasons = $this->reasonsOf(array_keys($modeOfGroup), $locale);
        $groups = [];

        foreach ($modeOfGroup as $groupId => $mode) {
            $names = [];
            $groupCountryIds = [];

            foreach ($destinationsOfGroup[$groupId] ?? [] as [$countryId, $stateId]) {
                $country = $countryNames[$countryId] ?? (string) $countryId;
                $names[] = null === $stateId ? $country : \sprintf('%s (%s)', $stateNames[$stateId] ?? (string) $stateId, $country);
                $groupCountryIds[] = $countryId;
            }

            $groups[] = [
                'mode' => $mode,
                'onlyServed' => DestinationRestriction::MODE_ALLOW === $mode,
                'reason' => $reasons[$groupId] ?? '',
                'destinations' => array_values(array_unique($names)),
                'countryIds' => array_values(array_unique($groupCountryIds)),
            ];
        }

        return $groups;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, string>
     */
    private function namesOf(CountryI18nQuery|StateI18nQuery $query, string $idColumn, string $titleColumn, array $ids, string $locale): array
    {
        $names = [];

        foreach ($query->filterById($ids)->filterByLocale($locale)->select([$idColumn, $titleColumn])->find() as $row) {
            $names[(int) $row[$idColumn]] = (string) $row[$titleColumn];
        }

        return $names;
    }

    /**
     * @param list<int> $groupIds
     *
     * @return array<int, string>
     */
    private function reasonsOf(array $groupIds, string $locale): array
    {
        $reasons = [];

        foreach (DestinationRestrictionI18nQuery::create()->filterById($groupIds)->filterByLocale($locale)->select([DestinationRestrictionI18nTableMap::COL_ID, DestinationRestrictionI18nTableMap::COL_REASON])->find() as $row) {
            $reasons[(int) $row[DestinationRestrictionI18nTableMap::COL_ID]] = trim((string) $row[DestinationRestrictionI18nTableMap::COL_REASON]);
        }

        return $reasons;
    }
}
