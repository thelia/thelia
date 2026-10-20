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

use Symfony\Contracts\Service\ResetInterface;
use Thelia\Model\Cart;
use Thelia\Model\DestinationRestriction;
use Thelia\Model\DestinationRestrictionCountryQuery;
use Thelia\Model\DestinationRestrictionI18nQuery;
use Thelia\Model\DestinationRestrictionQuery;
use Thelia\Model\Map\DestinationRestrictionCountryTableMap;
use Thelia\Model\Map\DestinationRestrictionI18nTableMap;
use Thelia\Model\Map\DestinationRestrictionTableMap;
use Thelia\Model\Map\ProductDestinationRestrictionTableMap;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductDestinationRestrictionQuery;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductQuery;

/**
 * Tells which products a destination refuses, the one rule behind the three refusals:
 * adding to the cart, choosing the delivery address and placing the order.
 *
 * A product travels to the delivery address; a download is licensed to the invoice
 * address. A group in "refused" mode refuses the destinations it lists, a group in
 * "only" mode refuses everything else; a country listed without a region covers all
 * its regions. A shop with no active group is asked nothing: the first answer is kept
 * for the request. The session is never read.
 */
final class DestinationRestrictionChecker implements ResetInterface
{
    private ?bool $anyActiveGroup = null;

    public function hasActiveGroup(): bool
    {
        return $this->anyActiveGroup ??= DestinationRestrictionQuery::create()->filterByActive(1)->exists();
    }

    public function reset(): void
    {
        $this->anyActiveGroup = null;
    }

    /**
     * @return list<RestrictedLine>
     */
    public function violationsFor(Cart $cart, ?Destination $delivery, ?Destination $billing, string $locale): array
    {
        if (!$this->hasActiveGroup()) {
            return [];
        }

        $productIds = [];

        foreach ($cart->getCartItems() as $cartItem) {
            $productIds[] = (int) $cartItem->getProductId();
        }

        return $this->violationsForProducts($productIds, $delivery, $billing, $locale);
    }

    /**
     * @param list<int> $productIds
     *
     * @return list<RestrictedLine>
     */
    public function violationsForProducts(array $productIds, ?Destination $delivery, ?Destination $billing, string $locale): array
    {
        $productIds = array_values(array_unique($productIds));

        if ([] === $productIds || (null === $delivery && null === $billing) || !$this->hasActiveGroup()) {
            return [];
        }

        // The active groups of these products, with their mode, in the order the merchant
        // set: when several groups refuse a product, the first one gives the reason.
        $groupsOfProduct = [];
        $modeOfGroup = [];

        foreach (ProductDestinationRestrictionQuery::create()
            ->filterByProductId($productIds)
            ->useDestinationRestrictionQuery()
                ->filterByActive(1)
                ->orderByPosition()
                ->orderById()
            ->endUse()
            ->select([ProductDestinationRestrictionTableMap::COL_PRODUCT_ID, ProductDestinationRestrictionTableMap::COL_DESTINATION_RESTRICTION_ID, DestinationRestrictionTableMap::COL_MODE])
            ->find() as $row) {
            $groupId = (int) $row[ProductDestinationRestrictionTableMap::COL_DESTINATION_RESTRICTION_ID];
            $groupsOfProduct[(int) $row[ProductDestinationRestrictionTableMap::COL_PRODUCT_ID]][] = $groupId;
            $modeOfGroup[$groupId] = (int) $row[DestinationRestrictionTableMap::COL_MODE];
        }

        if ([] === $groupsOfProduct) {
            return [];
        }

        /** @var array<int, list<array{int, ?int}>> $destinationsOfGroup group id => [country id, state id] */
        $destinationsOfGroup = [];

        foreach (DestinationRestrictionCountryQuery::create()
            ->filterByDestinationRestrictionId(array_keys($modeOfGroup))
            ->select([DestinationRestrictionCountryTableMap::COL_DESTINATION_RESTRICTION_ID, DestinationRestrictionCountryTableMap::COL_COUNTRY_ID, DestinationRestrictionCountryTableMap::COL_STATE_ID])
            ->find() as $row) {
            $stateId = $row[DestinationRestrictionCountryTableMap::COL_STATE_ID];
            $destinationsOfGroup[(int) $row[DestinationRestrictionCountryTableMap::COL_DESTINATION_RESTRICTION_ID]][] = [(int) $row[DestinationRestrictionCountryTableMap::COL_COUNTRY_ID], null !== $stateId ? (int) $stateId : null];
        }

        $virtual = [];

        foreach (ProductQuery::create()->filterById(array_keys($groupsOfProduct))->select([ProductTableMap::COL_ID, ProductTableMap::COL_VIRTUAL])->find() as $row) {
            $virtual[(int) $row[ProductTableMap::COL_ID]] = 1 === (int) $row[ProductTableMap::COL_VIRTUAL];
        }

        /** @var array<int, int> $refusingGroup product id => the first group, by position, that refuses it */
        $refusingGroup = [];

        foreach ($groupsOfProduct as $productId => $groupIds) {
            $destination = ($virtual[$productId] ?? false) ? $billing : $delivery;

            if (null === $destination) {
                continue;
            }

            foreach ($groupIds as $groupId) {
                if ($this->refuses($modeOfGroup[$groupId], $destinationsOfGroup[$groupId] ?? [], $destination)) {
                    $refusingGroup[$productId] = $groupId;

                    break;
                }
            }
        }

        if ([] === $refusingGroup) {
            return [];
        }

        $titles = $this->titlesOf(array_keys($refusingGroup), $locale);
        $reasons = $this->reasonsOf(array_values(array_unique($refusingGroup)), $locale);
        $lines = [];

        foreach ($refusingGroup as $productId => $groupId) {
            $lines[] = new RestrictedLine($productId, $titles[$productId] ?? (string) $productId, $reasons[$groupId] ?? '');
        }

        return $lines;
    }

    /**
     * @param list<array{int, ?int}> $destinations
     */
    private function refuses(int $mode, array $destinations, Destination $destination): bool
    {
        $listed = false;

        foreach ($destinations as [$countryId, $stateId]) {
            if ($countryId === $destination->countryId && (null === $stateId || $stateId === $destination->stateId)) {
                $listed = true;

                break;
            }
        }

        return DestinationRestriction::MODE_ALLOW === $mode ? !$listed : $listed;
    }

    /**
     * Titles of the products a refusal names, in the buyer's language or the default one
     * of the shop (FallbackTranslation). A product with no title there is named by its
     * reference.
     *
     * @param list<int> $productIds
     *
     * @return array<int, string> product id => title
     */
    private function titlesOf(array $productIds, string $locale): array
    {
        $titles = FallbackTranslation::valuesOf(ProductI18nQuery::create(), ProductI18nTableMap::COL_ID, ProductI18nTableMap::COL_LOCALE, ProductI18nTableMap::COL_TITLE, $productIds, $locale);
        $named = [];

        foreach (ProductQuery::create()->filterById($productIds)->select([ProductTableMap::COL_ID, ProductTableMap::COL_REF])->find() as $row) {
            $productId = (int) $row[ProductTableMap::COL_ID];
            $named[$productId] = $titles[$productId] ?? (string) $row[ProductTableMap::COL_REF];
        }

        return $named;
    }

    /**
     * @param list<int> $groupIds
     *
     * @return array<int, string> group id => reason, in the buyer's language or the default one of the shop
     */
    private function reasonsOf(array $groupIds, string $locale): array
    {
        return FallbackTranslation::valuesOf(DestinationRestrictionI18nQuery::create(), DestinationRestrictionI18nTableMap::COL_ID, DestinationRestrictionI18nTableMap::COL_LOCALE, DestinationRestrictionI18nTableMap::COL_REASON, $groupIds, $locale);
    }
}
