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

namespace Thelia\Domain\Catalog\Product;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Thelia\Model\Customer;

/**
 * A rule of a module that takes products out of the front catalog for the
 * current visitor: a catalog restricted per customer, a range kept for some
 * accounts.
 *
 * Rules are collected through the self::TAG tag (autoconfigured) and joined to
 * the private drops of the reserved operations by ProductVisibility. A product is
 * visible when every rule lets it through, in the readers that go through that
 * class, directly or through ReservedSaleVisibility: the front API collections and
 * items of products and sale elements, the product associations and videos,
 * the product page, the sale elements of a product, the quick order and a
 * purchase list loaded into it (not the purchase list itself), the product,
 * accessory and product_sale_elements loops, and the facets of the product
 * listing.
 *
 * What is read by the id of a product or of a sale element is not narrowed:
 * images, documents, prices, features, attribute combinations, categories and
 * associated contents. Nor is the cart: a rule narrows what is read, not what
 * can be bought, and a module that forbids buying a product also listens to
 * TheliaEvents::CART_ADDITEM with a priority above 128 (Thelia\Action\Cart::addItem).
 *
 * Only front readers ask: the back office and the admin API are never narrowed.
 * A rule that memoises what it reads per request implements ResetInterface, so
 * that a worker serving several requests does not carry one customer's catalog
 * over to the next.
 */
#[AutoconfigureTag(self::TAG)]
interface ProductVisibilityRuleInterface
{
    public const TAG = 'thelia.catalog.product_visibility_rule';

    /**
     * The condition is written into the statement as it is: nothing is bound.
     *
     *  - Every value in it is an integer read from the database or cast with
     *    (int), never a value taken from the request or the session.
     *  - No placeholder, neither `?` nor `:name`.
     *  - It depends on the given column alone: the query may read product,
     *    product_sale_elements, accessory or a subquery, and product is not
     *    necessarily joined. Reach product data through a correlated subquery
     *    whose tables are aliased, or the column it is given resolves against
     *    them, and return null before building an empty IN list:
     *
     *        if ([] === $closedBrandIds) {
     *            return null;
     *        }
     *
     *        return sprintf('NOT EXISTS (SELECT 1 FROM product rule_product WHERE rule_product.id = %s AND rule_product.brand_id IN (%s))',
     *            $productIdColumn, implode(', ', array_map('intval', $closedBrandIds)))
     *
     *  - One boolean expression with balanced parentheses; null, never '', when
     *    the rule hides nothing.
     *
     * @param string        $productIdColumn the qualified column holding the product id
     *                                       in the query being narrowed — `product.id`,
     *                                       `product_sale_elements.product_id`, …
     * @param Customer|null $customer        the visitor the catalog is read for, as
     *                                       the reserved operations see them (the
     *                                       session, then the bearer token of the
     *                                       front API); null for a visitor who is not
     *                                       signed in or checks out as a guest, whom
     *                                       the rule answers for as well
     *
     * @return string|null an SQL condition true for a product left visible, or
     *                     null when the rule hides nothing from the visitor
     */
    public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string;
}
