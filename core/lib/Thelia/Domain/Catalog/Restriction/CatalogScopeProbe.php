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

namespace Thelia\Domain\Catalog\Restriction;

use Propel\Runtime\Propel;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Model\Customer;
use Thelia\Model\Map\CatalogRestrictionTableMap;

/**
 * Whether anything in the shop restricts the catalog or assigns a price list, for what the
 * data access cache has to key on: a rule or a list assigned to a customer group is carried
 * by the group in the key, one assigned to a customer alone keeps that customer out of the
 * cache, as a discount of their own does.
 *
 * Existence checks only, asked once per request and per customer, and not at all in a shop
 * that has neither a restriction nor a turned-on price list. The dates and the state of
 * a price list are not looked at: a list turned off or out of its window costs an entry
 * per group, not a wrong price. The statements name integers and table names only.
 */
final class CatalogScopeProbe implements ResetInterface
{
    private ?bool $groupRestrictions = null;

    private ?bool $groupPriceLists = null;

    /** @var array<int, array{restriction: bool, priceList: bool}> */
    private array $customers = [];

    private bool $boundaryRead = false;

    private ?int $secondsToBoundary = null;

    public function __construct(
        private readonly CatalogActivityProbe $catalogActivityProbe,
    ) {
    }

    /**
     * A restriction is assigned to a customer group, whichever: what the catalog shows
     * then follows the group.
     */
    public function anyGroupIsRestricted(): bool
    {
        $this->readGroups();

        return $this->groupRestrictions ?? false;
    }

    /**
     * A price list is assigned to a customer group, whichever.
     */
    public function anyGroupHasPriceList(): bool
    {
        $this->readGroups();

        return $this->groupPriceLists ?? false;
    }

    /**
     * A restriction or a price list is assigned to this customer alone.
     */
    public function customerIsScopedAlone(?Customer $customer): bool
    {
        if (null === $customer || $customer->isNew() || 0 === (int) $customer->getId()) {
            return false;
        }

        $customerId = (int) $customer->getId();

        $this->customers[$customerId] ??= $this->readCustomer($customerId);

        return $this->customers[$customerId]['restriction'] || $this->customers[$customerId]['priceList'];
    }

    public function customerHasRestrictionAlone(?Customer $customer): bool
    {
        if (null === $customer || $customer->isNew() || 0 === (int) $customer->getId()) {
            return false;
        }

        $customerId = (int) $customer->getId();

        $this->customers[$customerId] ??= $this->readCustomer($customerId);

        return $this->customers[$customerId]['restriction'];
    }

    /**
     * The seconds before the first price list that is turned on opens or closes (its start or its
     * end date still ahead), null when none will: an answer that holds prices must not outlive
     * that moment, the prices it holds being the other ones from then on. Read once per request,
     * and not at all in a shop with no price list turned on.
     */
    public function secondsBeforeAPriceListOpensOrCloses(): ?int
    {
        if ($this->boundaryRead) {
            return $this->secondsToBoundary;
        }

        $this->boundaryRead = true;

        if (!$this->catalogActivityProbe->read()->hasPriceLists) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $statement = Propel::getConnection(CatalogRestrictionTableMap::DATABASE_NAME)->prepare(
            'SELECT MIN(bound) FROM (
                SELECT starts_at AS bound FROM `price_list` WHERE active = 1 AND starts_at > :starts_after
                UNION ALL
                SELECT ends_at AS bound FROM `price_list` WHERE active = 1 AND ends_at > :ends_after
             ) bounds'
        );
        $statement->execute(['starts_after' => $now->format('Y-m-d H:i:s'), 'ends_after' => $now->format('Y-m-d H:i:s')]);
        $bound = $statement->fetchColumn();

        if (false === $bound || null === $bound || '' === $bound) {
            return null;
        }

        return $this->secondsToBoundary = max(1, (new \DateTimeImmutable((string) $bound))->getTimestamp() - $now->getTimestamp());
    }

    public function reset(): void
    {
        $this->boundaryRead = false;
        $this->secondsToBoundary = null;
        $this->groupRestrictions = null;
        $this->groupPriceLists = null;
        $this->customers = [];
    }

    private function readGroups(): void
    {
        if (null !== $this->groupRestrictions && null !== $this->groupPriceLists) {
            return;
        }

        if (!$this->anythingIsScoped()) {
            $this->groupRestrictions = false;
            $this->groupPriceLists = false;

            return;
        }

        $statement = Propel::getConnection(CatalogRestrictionTableMap::DATABASE_NAME)->prepare(
            'SELECT
                EXISTS (SELECT 1 FROM `catalog_restriction` WHERE customer_group_id IS NOT NULL) AS group_restriction,
                EXISTS (SELECT 1 FROM `price_list_assignment` WHERE customer_group_id IS NOT NULL) AS group_price_list'
        );
        $statement->execute();

        /** @var array<string, int|string|null> $row */
        $row = $statement->fetch(\PDO::FETCH_ASSOC) ?: [];

        $this->groupRestrictions = 1 === (int) ($row['group_restriction'] ?? 0);
        $this->groupPriceLists = 1 === (int) ($row['group_price_list'] ?? 0);
    }

    /**
     * @return array{restriction: bool, priceList: bool}
     */
    private function readCustomer(int $customerId): array
    {
        if (!$this->anythingIsScoped()) {
            return ['restriction' => false, 'priceList' => false];
        }

        $statement = Propel::getConnection(CatalogRestrictionTableMap::DATABASE_NAME)->prepare(\sprintf(
            'SELECT
                EXISTS (SELECT 1 FROM `catalog_restriction` WHERE customer_id = %1$d) AS own_restriction,
                EXISTS (SELECT 1 FROM `price_list_assignment` WHERE customer_id = %1$d) AS own_price_list',
            $customerId,
        ));
        $statement->execute();

        /** @var array<string, int|string|null> $row */
        $row = $statement->fetch(\PDO::FETCH_ASSOC) ?: [];

        return ['restriction' => 1 === (int) ($row['own_restriction'] ?? 0), 'priceList' => 1 === (int) ($row['own_price_list'] ?? 0)];
    }

    /**
     * Whether a restriction or a turned-on price list exists at all: known from the statement the
     * front runs anyway, so that a shop with neither reads nothing here.
     */
    private function anythingIsScoped(): bool
    {
        $activity = $this->catalogActivityProbe->read();

        return $activity->hasCatalogRestrictions || $activity->hasPriceLists;
    }
}
