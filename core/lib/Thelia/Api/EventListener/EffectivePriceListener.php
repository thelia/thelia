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

namespace Thelia\Api\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Resource\ProductPrice as ProductPriceResource;
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Sale\CurrentCustomerProvider;

/**
 * Serves the price a catalog price rule or a reserved operation gives the caller, on
 * the front read of a sale element.
 *
 * Neither writes anything in the catalog - no `promo` flag, no `promo_price` -
 * because those two are what every visitor reads. Their price is therefore added
 * here, to the caller's own read. The page was warmed by
 * {@see EffectivePriceCollectionWarmer} when the read is a collection, so this costs
 * nothing per member; an item read resolves its one sale element.
 *
 * It runs after {@see ProductPriceCurrencyListener} has picked the price row of the
 * currency being browsed: the effective price is resolved in that same currency, so
 * the two have to be the same row.
 *
 * Admin reads are left alone: the back office edits the catalog, which these prices
 * are deliberately not part of.
 */
class EffectivePriceListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EffectivePriceCatalog $effectivePriceCatalog,
        private readonly CurrentCustomerProvider $currentCustomerProvider,
        private readonly ProductPriceCurrencyListener $productPriceCurrencyListener,
        private readonly PricingActivityChecker $activityChecker,
    ) {
    }

    public function applyEffectivePrice(ModelToResourceEvent $modelToResourceEvent): void
    {
        $resource = $modelToResourceEvent->getResource();

        if (!$resource instanceof ProductSaleElementsResource) {
            return;
        }

        $saleElementsId = $resource->getId();
        $prices = $resource->getProductPrices();

        if (null === $saleElementsId || [] === $prices || !self::isFrontRead($modelToResourceEvent->getContext())) {
            return;
        }

        // Asked before the visitor is looked up: reading the customer may open the
        // session, and an API request in a shop where nothing prices beyond the
        // catalog must stay stateless.
        if (!$this->activityChecker->hasActivePublicRule() && !$this->activityChecker->hasVisitorDependentPricing()) {
            return;
        }

        $effectivePrice = $this->effectivePriceCatalog->priceFor(
            $saleElementsId,
            $this->productPriceCurrencyListener->currentCurrency(),
            $this->currentCustomerProvider->getCurrentCustomer(),
        );

        if (null === $effectivePrice) {
            return;
        }

        foreach ($prices as $price) {
            if (!$price instanceof ProductPriceResource) {
                continue;
            }

            $price->setPromoPrice($effectivePrice->untaxedPromoPrice);
        }

        // The flag the theme reads to strike the catalog price through is now true
        // whatever the catalog says, and the rule decides whether the strike shows.
        $resource->setPromo(true);
        $resource->setDisplayInitialPrice($effectivePrice->displayInitialPrice);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['applyEffectivePrice', -10],
            ],
        ];
    }

    /**
     * A front read is one no admin group takes part in - the same rule
     * {@see ProductPriceCurrencyListener} applies, so a module resource embedding
     * sale elements is covered too.
     *
     * @param array<string, mixed> $context
     */
    public static function isFrontRead(array $context): bool
    {
        $groups = $context['groups'] ?? [];

        if (!\is_array($groups)) {
            $groups = [$groups];
        }

        $isFrontRead = false;

        foreach ($groups as $group) {
            if (!\is_string($group)) {
                continue;
            }

            if (str_starts_with($group, 'admin:')) {
                return false;
            }

            $isFrontRead = $isFrontRead || str_starts_with($group, 'front:');
        }

        return $isFrontRead;
    }
}
