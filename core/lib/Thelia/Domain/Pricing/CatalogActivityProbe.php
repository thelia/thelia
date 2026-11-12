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

namespace Thelia\Domain\Pricing;

use Propel\Runtime\Propel;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Map\CatalogPriceRuleTableMap;
use Thelia\Model\Sale;

/**
 * Whether anything in the shop prices beyond `product_price` or hides a category from
 * some visitors, asked of the database in one statement per request.
 *
 * Each question is an indexed existence check; asked one by one they cost a shop with
 * no rule, no reserved operation and no customer group as many round trips per
 * request, which the shop did not pay before the customer groups. Read together, the
 * whole front pays one statement, whatever it ends up asking.
 *
 * The dates of the rules and operations are not looked at here: their own readers
 * evaluate them, and a rule between two windows costs one indexed join, not a wrong
 * price. A discount of a group outside 0..100 counts for nothing, as in
 * {@see \Thelia\Model\CustomerGroup::effectiveDiscountRate()}.
 *
 * The existence check of a product that states a mode other than visible reads the index
 * `idx_product_price_display_mode`, not the table; the one of a cart line that still carries
 * the trace of a price list reads `idx_cart_item_price_list_id`.
 *
 * The statement names only constants and table names: nothing is bound.
 */
class CatalogActivityProbe implements ResetInterface
{
    private ?CatalogActivity $activity = null;

    /** @var array<int, array{priced: bool, discounted: bool}>|null */
    private ?array $groups = null;

    public function read(): CatalogActivity
    {
        return $this->activity ??= $this->query();
    }

    /**
     * The customer group has a price of its own for at least one sale element. Read, for all
     * the groups at once, the first time a group is asked about: a shop where no group prices
     * anything never gets here.
     */
    public function groupHasOwnPrices(int $customerGroupId): bool
    {
        return $this->groups()[$customerGroupId]['priced'] ?? false;
    }

    /**
     * The customer group discounts the catalog for its members (above 0, up to 100).
     */
    public function groupIsDiscounted(int $customerGroupId): bool
    {
        return $this->groups()[$customerGroupId]['discounted'] ?? false;
    }

    public function reset(): void
    {
        $this->activity = null;
        $this->groups = null;
    }

    /**
     * @return array<int, array{priced: bool, discounted: bool}>
     */
    private function groups(): array
    {
        if (null !== $this->groups) {
            return $this->groups;
        }

        $statement = Propel::getConnection(CatalogPriceRuleTableMap::DATABASE_NAME)->prepare(
            'SELECT
                customer_group.id AS id,
                EXISTS (
                    SELECT 1 FROM `customer_group_product_price` own
                    WHERE own.customer_group_id = customer_group.id AND (own.price IS NOT NULL OR own.promo_price IS NOT NULL)
                ) AS priced,
                (customer_group.discount > 0 AND customer_group.discount <= 100) AS discounted
             FROM `customer_group`'
        );
        $statement->execute();

        $this->groups = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $this->groups[(int) $row['id']] = ['priced' => 1 === (int) $row['priced'], 'discounted' => 1 === (int) $row['discounted']];
        }

        return $this->groups;
    }

    private function query(): CatalogActivity
    {
        $statement = Propel::getConnection(CatalogPriceRuleTableMap::DATABASE_NAME)->prepare(\sprintf(
            'SELECT
                EXISTS (SELECT 1 FROM `catalog_price_rule` WHERE active = 1 AND audience_mode = %1$d) AS public_rule,
                EXISTS (SELECT 1 FROM `catalog_price_rule` WHERE active = 1 AND audience_mode <> %1$d) AS named_rule,
                EXISTS (SELECT 1 FROM `sale` WHERE active = 1 AND audience_mode <> %2$d) AS reserved_sale,
                EXISTS (SELECT 1 FROM `customer_group_product_price` WHERE price IS NOT NULL OR promo_price IS NOT NULL) AS group_prices,
                EXISTS (SELECT 1 FROM `customer_group` WHERE discount > 0 AND discount <= 100) AS discounted_group,
                EXISTS (SELECT 1 FROM `customer_group` WHERE restricts_categories = 1) AS category_restrictions,
                (SELECT id FROM `customer_group` WHERE is_default = 1 ORDER BY id LIMIT 1) AS default_group_id,
                (SELECT display_taxed_price FROM `customer_group` WHERE is_default = 1 ORDER BY id LIMIT 1) AS default_group_displays_taxed_prices,
                EXISTS (
                    SELECT 1 FROM `customer_group` other
                    WHERE other.display_taxed_price <> (SELECT display_taxed_price FROM `customer_group` WHERE is_default = 1 ORDER BY id LIMIT 1)
                ) AS groups_display_prices_otherwise,
                EXISTS (SELECT 1 FROM `price_list` WHERE active = 1) AS price_lists,
                EXISTS (SELECT 1 FROM `catalog_restriction`) AS catalog_restrictions,
                (
                    (SELECT `value` FROM `config` WHERE `name` = %3$s) IN (%4$s)
                    OR EXISTS (SELECT 1 FROM `product` WHERE price_display_mode IN (%4$s))
                ) AS non_visible_price_display,
                EXISTS (
                    SELECT 1 FROM `price_list_assignment` assignment
                    INNER JOIN `price_list` assigned_list ON assigned_list.id = assignment.price_list_id AND assigned_list.active = 1
                    WHERE assignment.customer_group_id = (SELECT id FROM `customer_group` WHERE is_default = 1 ORDER BY id LIMIT 1)
                ) AS default_group_price_lists,
                EXISTS (
                    SELECT 1 FROM `catalog_restriction` default_restriction
                    WHERE default_restriction.customer_group_id = (SELECT id FROM `customer_group` WHERE is_default = 1 ORDER BY id LIMIT 1)
                ) AS default_group_catalog_restrictions,
                EXISTS (SELECT 1 FROM `cart_item` WHERE price_list_id IS NOT NULL) AS cart_lines_priced_by_price_lists',
            CatalogPriceRule::AUDIENCE_MODE_PUBLIC,
            Sale::AUDIENCE_MODE_PUBLIC,
            "'".PriceDisplayMode::CONFIG_KEY."'",
            "'".PriceDisplayMode::LoggedIn->value."', '".PriceDisplayMode::OnRequest->value."'",
        ));
        $statement->execute();

        /** @var array<string, int|string|null> $row */
        $row = $statement->fetch(\PDO::FETCH_ASSOC) ?: [];

        return new CatalogActivity(
            hasActivePublicRule: 1 === (int) ($row['public_rule'] ?? 0),
            hasActiveNamedRule: 1 === (int) ($row['named_rule'] ?? 0),
            hasActiveReservedSale: 1 === (int) ($row['reserved_sale'] ?? 0),
            hasCustomerGroupPrices: 1 === (int) ($row['group_prices'] ?? 0),
            hasDiscountedCustomerGroup: 1 === (int) ($row['discounted_group'] ?? 0),
            hasCategoryRestrictions: 1 === (int) ($row['category_restrictions'] ?? 0),
            defaultCustomerGroupId: null === ($row['default_group_id'] ?? null) || '' === $row['default_group_id'] ? null : (int) $row['default_group_id'],
            defaultCustomerGroupDisplaysTaxedPrices: 1 === (int) ($row['default_group_displays_taxed_prices'] ?? 1),
            hasCustomerGroupDisplayingPricesOtherwise: 1 === (int) ($row['groups_display_prices_otherwise'] ?? 0),
            hasPriceLists: 1 === (int) ($row['price_lists'] ?? 0),
            hasCatalogRestrictions: 1 === (int) ($row['catalog_restrictions'] ?? 0),
            hasNonVisiblePriceDisplay: 1 === (int) ($row['non_visible_price_display'] ?? 0),
            hasDefaultGroupPriceLists: 1 === (int) ($row['default_group_price_lists'] ?? 0),
            hasDefaultGroupCatalogRestrictions: 1 === (int) ($row['default_group_catalog_restrictions'] ?? 0),
            hasCartLinesPricedByPriceLists: 1 === (int) ($row['cart_lines_priced_by_price_lists'] ?? 0),
        );
    }
}
