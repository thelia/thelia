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

use Thelia\Model\CountryQuery;
use Thelia\Model\DestinationRestriction;
use Thelia\Model\DestinationRestrictionCountry;
use Thelia\Model\DestinationRestrictionCountryQuery;
use Thelia\Model\DestinationRestrictionQuery;
use Thelia\Model\ProductDestinationRestriction;
use Thelia\Model\ProductDestinationRestrictionQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\StateQuery;

/**
 * The writes on the destination restrictions, shared by the back office, the admin API
 * and the catalogue import. A product joins or leaves a group here, never through the
 * product form, whose update event rewrites every field it carries.
 */
final readonly class DestinationRestrictionFacade
{
    public function __construct(private DestinationRestrictionChecker $checker)
    {
    }

    /**
     * @param array<string, array{title?: ?string, reason?: ?string}> $wording locale => title and reason
     */
    public function save(?int $id, string $code, int $mode, bool $active, array $wording): DestinationRestriction
    {
        $code = trim($code);

        if ('' === $code) {
            throw new DestinationRestrictionException('A destination restriction needs a code.');
        }

        if (!\in_array($mode, [DestinationRestriction::MODE_DENY, DestinationRestriction::MODE_ALLOW], true)) {
            throw new DestinationRestrictionException(\sprintf('Unknown destination restriction mode %d.', $mode));
        }

        $group = null === $id ? new DestinationRestriction() : $this->group($id);
        $taken = DestinationRestrictionQuery::create()->filterByCode($code)->findOne();

        if (null !== $taken && $taken->getId() !== $group->getId()) {
            throw new DestinationRestrictionException(\sprintf('The code "%s" is already used by another destination restriction.', $code));
        }

        $group->setCode($code)->setMode($mode)->setActive((int) $active);

        foreach ($wording as $locale => $texts) {
            $group->setLocale($locale);

            if (\array_key_exists('title', $texts)) {
                $group->setTitle($texts['title']);
            }

            if (\array_key_exists('reason', $texts)) {
                $group->setReason($texts['reason']);
            }
        }

        $group->save();
        $this->checker->reset();

        return $group;
    }

    public function delete(int $id): void
    {
        $this->group($id)->delete();
        $this->checker->reset();
    }

    /**
     * A country, or one region of it. Listing a country already listed changes nothing.
     */
    public function addDestination(int $groupId, int $countryId, ?int $stateId = null): DestinationRestrictionCountry
    {
        $this->group($groupId);

        if (null === CountryQuery::create()->findPk($countryId)) {
            throw new DestinationRestrictionException(\sprintf('Unknown country %d.', $countryId));
        }

        if (null !== $stateId && null === StateQuery::create()->filterById($stateId)->filterByCountryId($countryId)->findOne()) {
            throw new DestinationRestrictionException(\sprintf('Region %d is not a region of country %d.', $stateId, $countryId));
        }

        $existing = DestinationRestrictionCountryQuery::create()
            ->filterByDestinationRestrictionId($groupId)
            ->filterByCountryId($countryId)
            ->filterByStateId($stateId)
            ->findOne();

        if (null !== $existing) {
            return $existing;
        }

        $destination = (new DestinationRestrictionCountry())
            ->setDestinationRestrictionId($groupId)
            ->setCountryId($countryId)
            ->setStateId($stateId);
        $destination->save();

        return $destination;
    }

    public function removeDestination(int $groupId, int $destinationId): void
    {
        DestinationRestrictionCountryQuery::create()
            ->filterByDestinationRestrictionId($groupId)
            ->filterById($destinationId)
            ->delete();
    }

    /**
     * Safe to call twice: a product already in the group stays in it once.
     */
    public function attachProduct(int $groupId, int $productId): ProductDestinationRestriction
    {
        $this->group($groupId);

        if (null === ProductQuery::create()->findPk($productId)) {
            throw new DestinationRestrictionException(\sprintf('Unknown product %d.', $productId));
        }

        $link = ProductDestinationRestrictionQuery::create()
            ->filterByDestinationRestrictionId($groupId)
            ->filterByProductId($productId)
            ->findOne();

        if (null === $link) {
            $link = (new ProductDestinationRestriction())->setDestinationRestrictionId($groupId)->setProductId($productId);
            $link->save();
        }

        return $link;
    }

    public function detachProduct(int $groupId, int $productId): void
    {
        ProductDestinationRestrictionQuery::create()
            ->filterByDestinationRestrictionId($groupId)
            ->filterByProductId($productId)
            ->delete();
    }

    /**
     * The catalogue import names both sides by their codes.
     */
    public function attachProductByReference(string $productReference, string $groupCode): ProductDestinationRestriction
    {
        $product = ProductQuery::create()->findOneByRef($productReference)
            ?? throw new DestinationRestrictionException(\sprintf('Unknown product reference "%s".', $productReference));
        $group = DestinationRestrictionQuery::create()->findOneByCode($groupCode)
            ?? throw new DestinationRestrictionException(\sprintf('Unknown destination restriction code "%s".', $groupCode));

        return $this->attachProduct((int) $group->getId(), (int) $product->getId());
    }

    private function group(int $id): DestinationRestriction
    {
        return DestinationRestrictionQuery::create()->findPk($id)
            ?? throw new DestinationRestrictionException(\sprintf('Unknown destination restriction %d.', $id));
    }
}
