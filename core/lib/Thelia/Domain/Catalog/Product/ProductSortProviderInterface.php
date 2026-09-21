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

/**
 * Declares one way of ordering a product listing: what it is called, where it sits in the
 * selector, and the parameters it sends to the product collection of the API.
 *
 * A theme ships the orders that read native product columns — price, date, title — because
 * those hold on any shop. An order that reads what a module writes cannot be one of them: on a
 * shop without that module the entry would still be offered, and picking it would send a
 * parameter nothing implements, listing the whole catalogue in the merchant's own order under a
 * heading claiming it is sorted. Such an order therefore belongs to the module that owns the
 * data, hence this interface.
 *
 * Implementations are auto-registered: any service implementing this interface is tagged
 * `thelia.catalog.product_sort` and read by the active theme. Only the services of an activated
 * module reach the container, so a module that is installed but turned off offers nothing, with
 * no check to write.
 *
 * A provider owns both halves of its order. The parameters returned by parameters() have to be
 * answered by a filter the same module registers on the product collection — an
 * ApiPlatform\Metadata\AsOperationMutator appending it to `_api_/front/products_get_collection`
 * is what lets a module add one without the core knowing it exists. A provider whose parameters
 * nothing implements is the very failure this interface is here to prevent.
 */
#[AutoconfigureTag('thelia.catalog.product_sort')]
interface ProductSortProviderInterface
{
    /**
     * The value this order is named by in the query string of the listing.
     *
     * It travels in shared, bookmarked and indexed urls: it can be added, it must not be
     * renamed afterwards. It must not collide with an order the theme ships nor with another
     * module's — prefer the module code as a prefix, for instance `loyalty_most_redeemed`.
     *
     * Should two providers claim the same value, the last one read replaces the earlier: that
     * is how a project deliberately substitutes its own order for one already offered.
     */
    public function value(): string;

    /**
     * The label of the entry, ready to display.
     *
     * Translated by the provider, not by the theme: Twig's |trans reaches the Symfony translator,
     * which on the front office knows the catalogs of the active theme only, so a string a module
     * owns has to be translated against the module's own domain before it leaves. A theme passing
     * this through its catalogs anyway gets it back untouched, there being no key to match.
     */
    public function title(): string;

    /**
     * Where the entry sits in the selector, on the scale the theme gives its own orders. Two
     * providers sharing a rank keep the order they were declared in.
     */
    public function position(): int;

    /**
     * The ordering parameters of the API product query, as a query parameter to value map, for
     * instance `['order[rating]' => 'desc']`.
     *
     * The theme adds its own tiebreaker so that paginating stays stable — products routinely
     * share a rank, and a listing without a total order lets one repeat on a page and vanish
     * from another.
     *
     * @return array<string, string>
     */
    public function parameters(): array;
}
