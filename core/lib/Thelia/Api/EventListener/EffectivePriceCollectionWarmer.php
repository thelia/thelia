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
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Warms the effective prices of a whole page before its members are transformed,
 * so that {@see EffectivePriceListener} answers each of them from memory.
 *
 * A page of sale elements is warmed as is; a page of products is warmed through the
 * sale elements it embeds, read in one statement. Any other collection is left
 * alone: the per-member listener still answers, one lookup at a time.
 */
class EffectivePriceCollectionWarmer implements EventSubscriberInterface
{
    public function __construct(
        private readonly EffectivePriceCatalog $effectivePriceCatalog,
        private readonly CurrentCustomerProvider $currentCustomerProvider,
        private readonly ProductPriceCurrencyListener $productPriceCurrencyListener,
        private readonly PricingActivityChecker $activityChecker,
    ) {
    }

    public function warm(CollectionModelsLoadedEvent $event): void
    {
        if (!EffectivePriceListener::isFrontRead($event->getContext())) {
            return;
        }

        // Asked before the visitor is looked up: reading the customer may open the
        // session, and an API request in a shop where nothing prices beyond the
        // catalog must stay stateless.
        if (!$this->activityChecker->hasActivePublicRule() && !$this->activityChecker->hasVisitorDependentPricing()) {
            return;
        }

        // Read before the visitor is looked up: this event is fired for every Propel
        // collection, and a page of brands or of countries carries no price. Looking
        // the customer up there would open a session, and stop the response from
        // being cached, on collections a rule can never change.
        $productSaleElementsIds = $this->saleElementIdsOf($event->getModels());

        if ([] === $productSaleElementsIds) {
            return;
        }

        $customer = $this->currentCustomerProvider->getCurrentCustomer();

        // Gathering the sale elements of a page of products is one query: not spent
        // in a shop where nothing prices for this visitor beyond the catalog.
        if (!$this->effectivePriceCatalog->pricesAnythingFor($customer)) {
            return;
        }

        $this->effectivePriceCatalog->warm(
            $productSaleElementsIds,
            $this->productPriceCurrencyListener->currentCurrency(),
            $customer,
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [CollectionModelsLoadedEvent::class => 'warm'];
    }

    /**
     * @param list<object> $models
     *
     * @return list<int>
     */
    private function saleElementIdsOf(array $models): array
    {
        $productSaleElementsIds = [];
        $productIds = [];

        foreach ($models as $model) {
            if ($model instanceof ProductSaleElements) {
                $productSaleElementsIds[] = (int) $model->getId();
            } elseif ($model instanceof Product) {
                $productIds[] = (int) $model->getId();
            }
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

        return $productSaleElementsIds;
    }
}
