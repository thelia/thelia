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
use Thelia\Domain\Catalog\Product\ProductTaxedPriceResolver;
use Thelia\Domain\Catalog\Product\VisitorPriceReader;
use Thelia\Domain\Pricing\CustomerPrice\ResolvedPrice;
use Thelia\Model\ProductSaleElements;

/**
 * Serves what the caller pays for a sale element on the front read of it: the price of
 * the group they belong to, the catalog price rules and the reserved operations that
 * price it for them, then the discount of their group and their own - the same price the
 * cart charges.
 *
 * None of it is written in the catalog - no `promo` flag, no `promo_price` - because
 * those are what every visitor reads. It is therefore added here, to the caller's own
 * read: the customer is the one the request authenticated as (the token of an API call,
 * the session of the front office calling it in-process), and a visitor reads the public
 * prices. The page was warmed by {@see EffectivePriceCollectionWarmer} when the read is a
 * collection, so this costs nothing per member; an item read resolves its one sale
 * element.
 *
 * It runs after {@see ProductPriceCurrencyListener} has picked the price row of the
 * currency being browsed: the price is resolved in that same currency, so the two have
 * to be the same row. `price` is the price the promotion is shown against, `promoPrice`
 * what is charged while `promo` is true.
 *
 * Admin reads are left alone: the back office edits the catalog, which these prices
 * are deliberately not part of.
 */
class EffectivePriceListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly VisitorPriceReader $visitorPriceReader,
        private readonly ProductPriceCurrencyListener $productPriceCurrencyListener,
        private readonly ProductTaxedPriceResolver $productTaxedPriceResolver,
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

        $resolvedPrice = $this->visitorPriceReader->resolve(
            [$saleElementsId],
            $this->productPriceCurrencyListener->currentCurrency(),
        )[$saleElementsId] ?? null;

        if ($resolvedPrice instanceof ResolvedPrice) {
            foreach ($prices as $price) {
                if ($price instanceof ProductPriceResource) {
                    $price
                        ->setPrice($resolvedPrice->untaxedPrice)
                        ->setPromoPrice($resolvedPrice->untaxedPromoPrice);
                }
            }

            // The flag the theme reads to strike the catalog price through says what the
            // visitor pays, whatever the catalog says. Whether the strike shows is the call of
            // the catalog while it prices alone (a null the theme reads as its default), and
            // of what priced the sale element otherwise.
            $resource->setPromo($resolvedPrice->isPromo);

            if (ResolvedPrice::SOURCE_BASE !== $resolvedPrice->source) {
                $resource->setDisplayInitialPrice($resolvedPrice->displayInitialPrice);
            }
        }

        // The sale element itself, not the model the read started from: the event of a sale
        // element embedded in a product carries the product.
        $this->publishTaxedPrices($resource->getPropelModel(), $prices);
    }

    /**
     * The prices with their taxes, read once the price the visitor pays is settled: the card
     * of a product and its page show the same taxed figure whatever priced the sale element.
     * The products of a page were preloaded by {@see EffectivePriceCollectionWarmer}.
     *
     * @param array<mixed> $prices
     */
    private function publishTaxedPrices(?object $model, array $prices): void
    {
        if (!$model instanceof ProductSaleElements) {
            return;
        }

        $productId = (int) $model->getProductId();

        foreach ($prices as $price) {
            if ($price instanceof ProductPriceResource) {
                $price
                    ->setTaxedPrice($this->productTaxedPriceResolver->taxedPrice($productId, $price->getPrice()))
                    ->setTaxedPromoPrice($this->productTaxedPriceResolver->taxedPrice($productId, $price->getPromoPrice()));
            }
        }
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
