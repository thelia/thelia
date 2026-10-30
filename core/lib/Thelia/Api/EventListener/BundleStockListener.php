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
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Api\Bridge\Propel\Event\CollectionModelsLoadedEvent;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Domain\Catalog\Bundle\BundleCatalogState;
use Thelia\Domain\Catalog\Bundle\BundleDisplayedStock;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;

/**
 * Serves, on the front read of the sale element of a bundle, the number of bundles the
 * components allow instead of the quantity of the sale element, which stays at zero. It runs
 * after the sale unit listener: a bundle never keeps a stock of its own. Admin reads are left
 * alone, the back office edits the counter of the catalog.
 *
 * A shop without a bundle asks nothing. Otherwise a page is looked up once, when its models
 * are loaded (the way {@see EffectivePriceCollectionWarmer} warms the prices), and each member
 * is answered from memory; a sale element read alone looks up its own product. What is kept
 * lasts the request ({@see reset()}).
 */
final class BundleStockListener implements EventSubscriberInterface, ResetInterface
{
    /** @var array<int, int|null> displayed stock by product id, null for a product that is not a bundle */
    private array $displayedByProduct = [];

    public function __construct(
        private readonly BundleCatalogState $bundleCatalogState,
        private readonly BundleDisplayedStock $bundleDisplayedStock,
    ) {
    }

    public function warm(CollectionModelsLoadedEvent $event): void
    {
        if (!$this->bundleCatalogState->hasBundles() || !EffectivePriceListener::isFrontRead($event->getContext())) {
            return;
        }

        $productIds = [];

        foreach ($event->getModels() as $model) {
            if ($model instanceof ProductSaleElements) {
                $productIds[] = (int) $model->getProductId();
            } elseif ($model instanceof Product) {
                $productIds[] = (int) $model->getId();
            }
        }

        $productIds = array_values(array_diff(array_unique($productIds), array_keys($this->displayedByProduct)));

        if ([] === $productIds) {
            return;
        }

        $displayed = $this->bundleDisplayedStock->forProducts($productIds);

        foreach ($productIds as $productId) {
            $this->displayedByProduct[$productId] = $displayed[$productId] ?? null;
        }
    }

    public function applyBundleStock(ModelToResourceEvent $modelToResourceEvent): void
    {
        $resource = $modelToResourceEvent->getResource();
        $model = $modelToResourceEvent->getModel();

        if (!$resource instanceof ProductSaleElementsResource || !$model instanceof ProductSaleElements) {
            return;
        }

        if (!$this->bundleCatalogState->hasBundles() || !EffectivePriceListener::isFrontRead($modelToResourceEvent->getContext())) {
            return;
        }

        $productId = (int) $model->getProductId();

        if (!\array_key_exists($productId, $this->displayedByProduct)) {
            $this->displayedByProduct[$productId] = $this->bundleDisplayedStock->forProducts([$productId])[$productId] ?? null;
        }

        if (null !== $this->displayedByProduct[$productId]) {
            $resource->setQuantity($this->displayedByProduct[$productId]);
        }
    }

    public function reset(): void
    {
        $this->displayedByProduct = [];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CollectionModelsLoadedEvent::class => 'warm',
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['applyBundleStock', -20],
            ],
        ];
    }
}
