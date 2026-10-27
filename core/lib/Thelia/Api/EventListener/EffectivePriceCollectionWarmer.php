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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\CollectionModelsLoadedEvent;
use Thelia\Domain\Catalog\Product\ProductTaxedPriceResolver;
use Thelia\Domain\Catalog\Product\VisitorPriceReader;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Resolves what the caller pays for a whole page before its members are transformed,
 * so that {@see EffectivePriceListener} answers each of them from memory.
 *
 * A page of sale elements is resolved as is; a page of products is resolved through the
 * sale elements it embeds, read in one statement. Any other collection is left alone:
 * the per-member listener still answers, one lookup at a time.
 *
 * The products of the page are also loaded with their tax rule, in one statement, for the
 * taxed prices the listener publishes: that is what keeps a listing from costing a query
 * per product, a shop that prices nothing beyond the catalog included.
 */
class EffectivePriceCollectionWarmer implements EventSubscriberInterface
{
    public function __construct(
        private readonly VisitorPriceReader $visitorPriceReader,
        private readonly ProductPriceCurrencyListener $productPriceCurrencyListener,
        private readonly ProductTaxedPriceResolver $productTaxedPriceResolver,
    ) {
    }

    public function warm(CollectionModelsLoadedEvent $event): void
    {
        if (!EffectivePriceListener::isFrontRead($event->getContext())) {
            return;
        }

        $productSaleElementsIds = [];
        $productIds = [];
        $taxedProductIds = [];

        // Read before the visitor is looked up: this event is fired for every Propel
        // collection, and a page of brands or of countries carries no price.
        foreach ($event->getModels() as $model) {
            if ($model instanceof ProductSaleElements) {
                $productSaleElementsIds[] = (int) $model->getId();
                $taxedProductIds[] = (int) $model->getProductId();
            } elseif ($model instanceof Product) {
                $productIds[] = (int) $model->getId();
                $taxedProductIds[] = (int) $model->getId();
            }
        }

        if ([] === $productSaleElementsIds && [] === $productIds) {
            return;
        }

        $this->productTaxedPriceResolver->preload($taxedProductIds);

        // Gathering the sale elements of a page of products is one query: not spent in a
        // shop where nothing prices for this visitor beyond the catalog.
        if (!$this->visitorPriceReader->mayPriceBeyondTheCatalog()) {
            return;
        }

        if ([] !== $productIds) {
            /** @var list<int|string> $ids */
            $ids = ProductSaleElementsQuery::create()
                ->filterByProductId($productIds, Criteria::IN)
                ->select('Id')
                ->find()
                ->getData();

            $productSaleElementsIds = [...$productSaleElementsIds, ...array_map('intval', $ids)];
        }

        $this->visitorPriceReader->resolve($productSaleElementsIds, $this->productPriceCurrencyListener->currentCurrency());
    }

    public static function getSubscribedEvents(): array
    {
        return [CollectionModelsLoadedEvent::class => 'warm'];
    }
}
