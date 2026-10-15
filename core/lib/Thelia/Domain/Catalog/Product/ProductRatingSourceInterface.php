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
 * Tells which products customers rated well, for the rating facet of a product listing.
 *
 * The core holds no review: the ratings belong to the module that collects them. That module
 * implements this interface, and the facet offers "4 stars & up", "3 stars & up" and "2 stars
 * & up" on the listings. Without any implementation the facet is not offered at all, neither
 * on the listing nor on the back-office screen where the merchant arranges the facets: a shop
 * without reviews has nothing to filter by.
 *
 * Implementations are auto-registered: any service implementing this interface is tagged
 * `thelia.catalog.product_rating_source`. Only the services of an activated module reach the
 * container, so a module that is installed but turned off offers nothing. A shop runs one
 * review module; should several implement this interface, the first one read answers.
 *
 * The rating a source compares is the one it shows to the buyer, the average of the reviews
 * it publishes: a facet that disagrees with the stars printed on the product card is a facet
 * nobody trusts.
 */
#[AutoconfigureTag('thelia.catalog.product_rating_source')]
interface ProductRatingSourceInterface
{
    /**
     * The products whose average rating reaches $minimumRating.
     *
     * The rating is read on a five-star scale whatever the scale the module collects on: a
     * module rating out of ten compares its average divided by two. A product nobody rated
     * reaches no threshold.
     *
     * @param list<int>|null $amongProductIds the products to look at; null for every product
     *
     * @return list<int>
     */
    public function productIdsRatedAtLeast(float $minimumRating, ?array $amongProductIds = null): array;
}
