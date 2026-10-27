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

namespace Thelia\Api\Service\API;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Api\EventListener\ProductPriceCurrencyListener;
use Thelia\Domain\Catalog\Category\CategoryVisibility;
use Thelia\Domain\Catalog\Product\ProductTaxedPriceResolver;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\SaleUnit\Pricing\SaleUnitPricingActivityChecker;
use Thelia\Domain\SaleUnit\Stock\SaleUnitStockActivityChecker;
use Thelia\Model\Customer;

/**
 * Cross-request cache for the data access layer.
 *
 * Sits behind the per-request {@see ResourceMemoizer}: on a memoizer miss the
 * normalized payload of a read-stable front resource is looked up here, so it
 * survives across requests. Disabled by default; only user-independent paths
 * (the configured allow list) are eligible, and the whole pool is flushed on
 * any catalog change (see ResourceCacheInvalidationListener).
 *
 * The key holds the path, the format and the locale, which is sound only while the
 * answer is the same for everybody. A price is not: it follows the customer group of
 * the one asking, so the price paths hold the id of that group in their key, and
 * visitors and customers of one group share an entry while a customer of another
 * group never reads it. A shop that prices nothing per group keeps the plain key, with
 * one exception: the price paths also hold the country and the state the taxed price is
 * calculated for and the currency it is written in, whatever the group, since two visitors
 * of one group delivered to two countries are not served the same figure. What
 * a group cannot say stays out of the cache, for the customer it concerns alone: a
 * customer who has a discount of their own is charged less than the rest of their
 * group, and a rule or an operation reserved for named customers or groups may price
 * a customer, which a visitor it never reaches is not. The visitors stay on the cache
 * through all of it, the customer being the one the request authenticated as.
 *
 * What the catalog shows follows the group as well: a group that lists the categories it
 * may browse is shown those, their products and what hangs on them, and no other one, so
 * while one group has a list the categories, the products, their sale elements and the
 * images of the products hold the group in their key too, whether or not anything is
 * priced per group.
 *
 * Some answers are not the same for everybody whatever the group, and bypass the paths they
 * concern for as long as they hold: a product visibility rule declared by a module, which
 * narrows the catalog per visitor and may look at anything of them, bypasses the products
 * and what hangs on them; a sale unit that prices or stocks on its own, the key carrying no
 * unit, so that a price or a stock cached while selling from one unit is not served to
 * a request selling from another, nor to one selling from none, bypasses the price paths.
 */
readonly class ResourceCache
{
    private const CATEGORY_PREFIX = '/api/front/categories';

    /**
     * The paths that carry no price but hang on a product: what the visitor may see of them
     * is what ProductVisibility lets through for the product they belong to.
     */
    private const PRODUCT_ATTACHMENT_PREFIXES = [
        '/api/front/product_images',
        '/api/front/product_sale_elements_product_image',
    ];

    /** Prefix of the group id a price path holds in its key. */
    private const GROUP_SCOPE = '_group';

    /**
     * @param string[] $allowedPrefixes
     * @param string[] $visitorDependentPricePrefixes
     */
    public function __construct(
        #[Autowire(service: 'thelia.cache.data_access')]
        private CacheItemPoolInterface $pool,
        #[Autowire(param: 'thelia.api.data_access.cache.enabled')]
        private bool $enabled,
        #[Autowire(param: 'thelia.api.data_access.cache.ttl')]
        private int $ttl,
        #[Autowire(param: 'thelia.api.data_access.cache.allowed_prefixes')]
        private array $allowedPrefixes,
        #[Autowire(param: 'thelia.api.data_access.cache.visitor_dependent_price_prefixes')]
        private array $visitorDependentPricePrefixes,
        private PricingActivityChecker $pricingActivityChecker,
        private CatalogActivityProbe $catalogActivityProbe,
        private CustomerGroupResolver $customerGroupResolver,
        private ?ProductVisibility $productVisibility = null,
        private ?SaleUnitPricingActivityChecker $saleUnitPricingActivityChecker = null,
        private ?SaleUnitStockActivityChecker $saleUnitStockActivityChecker = null,
        private ?CategoryVisibility $categoryVisibility = null,
        private ?CurrentCustomerProvider $currentCustomerProvider = null,
        private ?ProductTaxedPriceResolver $taxedPriceResolver = null,
        private ?ProductPriceCurrencyListener $priceCurrency = null,
    ) {
    }

    /**
     * @param callable():(object|array|null) $compute
     */
    public function remember(string $key, string $path, callable $compute): object|array|null
    {
        $scope = $this->enabled ? $this->scopeOf($path) : null;

        if (null === $scope) {
            return $compute();
        }

        $item = $this->pool->getItem($key.$scope);
        if ($item->isHit()) {
            return $item->get();
        }

        $value = $compute();

        // Never persist empty results: a transient miss (access denied, not
        // found) must not become a sticky cached value.
        if ($value !== null) {
            $item->set($value)->expiresAfter($this->ttl);
            $this->pool->save($item);
        }

        return $value;
    }

    public function clear(): void
    {
        $this->pool->clear();
    }

    /**
     * What this path adds to the key of its answer: nothing when the answer is the same
     * for everybody, the group of the one asking when the price follows it, and null
     * when the answer must not be cached at all for the one asking.
     */
    private function scopeOf(string $path): ?string
    {
        foreach ($this->allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $this->scopeOfAllowedPath($path);
            }
        }

        return null;
    }

    private function scopeOfAllowedPath(string $path): ?string
    {
        if ($this->startsWithOneOf($path, $this->visitorDependentPricePrefixes)) {
            return $this->scopeOfPricePath();
        }

        // The categories a group may browse follow its own list: the categories are not
        // priced, so they are not in the list above.
        if (str_starts_with($path, self::CATEGORY_PREFIX)) {
            return $this->scopeOfVisibility();
        }

        if ($this->startsWithOneOf($path, self::PRODUCT_ATTACHMENT_PREFIXES)) {
            return $this->scopeOfProductAttachmentPath();
        }

        return '';
    }

    /**
     * The questions are asked only for a path a price can travel on, and memoised for the
     * request: a shop with no rule, no operation, no group price, no discount and no closed
     * category pays the one statement of the activity probe for its whole cache.
     */
    private function scopeOfPricePath(): ?string
    {
        $group = $this->scopeOfPriceGroup();

        return null === $group ? null : $group.$this->scopeOfTaxedPriceDestination();
    }

    /**
     * The country and the state the taxed price is calculated for, and the currency it is
     * written in: two visitors of one group, delivered to two countries or browsing in two
     * currencies, are not served the same figure.
     */
    private function scopeOfTaxedPriceDestination(): string
    {
        if (null === $this->taxedPriceResolver || null === $this->priceCurrency) {
            return '';
        }

        return \sprintf('_to%s_in%d', $this->taxedPriceResolver->destinationKey(), $this->priceCurrency->currentCurrency()->getId());
    }

    private function scopeOfPriceGroup(): ?string
    {
        // The sale units share this bypass: the key carries no unit either.
        if (true === $this->productVisibility?->hasModuleRules()
            || true === $this->saleUnitPricingActivityChecker?->anySaleUnitPricesAnything()
            || true === $this->saleUnitStockActivityChecker?->anySaleUnitKeepsItsOwnStock()) {
            return null;
        }

        $customer = $this->currentCustomerProvider?->getCurrentCustomer();

        if (!$this->pricingActivityChecker->pricesDependOnWhoIsAsking($customer) && !$this->categoriesAreClosedToSomeGroup()) {
            return '';
        }

        if (null !== $customer && $this->customerIsPricedBeyondTheirGroup($customer)) {
            return null;
        }

        return self::GROUP_SCOPE.$this->customerGroupResolver->groupIdOf($customer);
    }

    /**
     * What hangs on a product is shown to whoever may see the product: a rule of a module
     * leaves it out of the cache, an operation reserved for named customers may hide the
     * product from the visitors it does not name, and a closed category hides it per group.
     */
    private function scopeOfProductAttachmentPath(): ?string
    {
        if (true === $this->productVisibility?->hasModuleRules()) {
            return null;
        }

        $customer = $this->currentCustomerProvider?->getCurrentCustomer();

        if (null !== $customer && $this->catalogActivityProbe->read()->hasActiveReservedSale) {
            return null;
        }

        return $this->scopeOfVisibility();
    }

    /**
     * The key of an answer that differs between two groups only by the categories closed
     * to one of them.
     */
    private function scopeOfVisibility(): string
    {
        if (!$this->categoriesAreClosedToSomeGroup()) {
            return '';
        }

        return self::GROUP_SCOPE.$this->customerGroupResolver->groupIdOf($this->currentCustomerProvider?->getCurrentCustomer());
    }

    private function categoriesAreClosedToSomeGroup(): bool
    {
        return true === $this->categoryVisibility?->hasRestrictions();
    }

    /**
     * @param list<string> $prefixes
     */
    private function startsWithOneOf(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether something prices this customer other than the group they belong to: a
     * discount of their own, which is a column of the customer with no table to ask whether
     * anyone has one, or a rule or an operation reserved for named customers or groups.
     */
    private function customerIsPricedBeyondTheirGroup(Customer $customer): bool
    {
        if ($this->pricingActivityChecker->hasDiscountedCustomer($customer)) {
            return true;
        }

        $activity = $this->catalogActivityProbe->read();

        return $activity->hasActiveNamedRule || $activity->hasActiveReservedSale;
    }
}
