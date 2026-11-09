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

namespace Thelia\Domain\Search\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Thelia\Domain\Search\Provider\BrandDocumentProvider;
use Thelia\Domain\Search\Provider\CategoryDocumentProvider;
use Thelia\Domain\Search\Provider\ContentDocumentProvider;
use Thelia\Domain\Search\Provider\FolderDocumentProvider;
use Thelia\Domain\Search\Provider\ProductDocumentProvider;
use Thelia\Domain\Search\SearchEngineRegistry;
use Thelia\Domain\Search\SearchIndexingQueue;
use Thelia\Model\Brand;
use Thelia\Model\BrandI18n;
use Thelia\Model\Event\BrandEvent;
use Thelia\Model\Event\BrandI18nEvent;
use Thelia\Model\Event\CategoryEvent;
use Thelia\Model\Event\CategoryI18nEvent;
use Thelia\Model\Event\ContentEvent;
use Thelia\Model\Event\ContentI18nEvent;
use Thelia\Model\Event\FolderEvent;
use Thelia\Model\Event\FolderI18nEvent;
use Thelia\Model\Event\ProductEvent;
use Thelia\Model\Event\ProductI18nEvent;
use Thelia\Model\Event\ProductSaleElementsEvent;
use Thelia\Model\Map\BrandI18nTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;

final readonly class SearchIndexingListener implements EventSubscriberInterface
{
    private const array FAMILIES = [
        ProductEvent::POST_DELETE => ProductDocumentProvider::FAMILY,
        ProductI18nEvent::POST_SAVE => ProductDocumentProvider::FAMILY,
        ProductI18nEvent::POST_DELETE => ProductDocumentProvider::FAMILY,
        CategoryEvent::POST_SAVE => CategoryDocumentProvider::FAMILY,
        CategoryEvent::POST_DELETE => CategoryDocumentProvider::FAMILY,
        CategoryI18nEvent::POST_SAVE => CategoryDocumentProvider::FAMILY,
        CategoryI18nEvent::POST_DELETE => CategoryDocumentProvider::FAMILY,
        ContentEvent::POST_SAVE => ContentDocumentProvider::FAMILY,
        ContentEvent::POST_DELETE => ContentDocumentProvider::FAMILY,
        ContentI18nEvent::POST_SAVE => ContentDocumentProvider::FAMILY,
        ContentI18nEvent::POST_DELETE => ContentDocumentProvider::FAMILY,
        FolderEvent::POST_SAVE => FolderDocumentProvider::FAMILY,
        FolderEvent::POST_DELETE => FolderDocumentProvider::FAMILY,
        FolderI18nEvent::POST_SAVE => FolderDocumentProvider::FAMILY,
        FolderI18nEvent::POST_DELETE => FolderDocumentProvider::FAMILY,
        BrandEvent::POST_SAVE => BrandDocumentProvider::FAMILY,
        BrandEvent::POST_DELETE => BrandDocumentProvider::FAMILY,
        BrandI18nEvent::POST_SAVE => BrandDocumentProvider::FAMILY,
        BrandI18nEvent::POST_DELETE => BrandDocumentProvider::FAMILY,
    ];

    private const array INDEXED_PRODUCT_COLUMNS = [ProductTableMap::COL_REF, ProductTableMap::COL_VISIBLE, ProductTableMap::COL_BRAND_ID];

    private const array INDEXED_SALE_ELEMENTS_COLUMNS = [ProductSaleElementsTableMap::COL_REF, ProductSaleElementsTableMap::COL_EAN_CODE, ProductSaleElementsTableMap::COL_PRODUCT_ID];

    /** @var \WeakMap<Product, true> */
    private \WeakMap $productsToIndex;

    public function __construct(
        private SearchEngineRegistry $engines,
        private SearchIndexingQueue $queue,
        private LoggerInterface $logger,
    ) {
        $this->productsToIndex = new \WeakMap();
    }

    public static function getSubscribedEvents(): array
    {
        $subscribed = [
            KernelEvents::TERMINATE => ['flush', 0],
            ConsoleEvents::TERMINATE => ['flush', 0],
            WorkerMessageHandledEvent::class => ['flush', 0],
            WorkerMessageFailedEvent::class => ['flush', 0],
            ProductEvent::PRE_SAVE => ['onProductPreSave', 0],
            ProductEvent::POST_SAVE => ['onProductPostSave', 0],
            ProductSaleElementsEvent::PRE_SAVE => ['onSaleElementsPreSave', 0],
            ProductSaleElementsEvent::POST_DELETE => ['onSaleElementsWrite', 0],
            BrandEvent::PRE_DELETE => ['onBrandProductsChange', 0],
            BrandI18nEvent::PRE_SAVE => ['onBrandTranslationPreSave', 0],
        ];

        foreach (array_keys(self::FAMILIES) as $eventName) {
            $subscribed[$eventName] = ['onModelWrite', 0];
        }

        $subscribed[BrandI18nEvent::POST_DELETE] = [['onModelWrite', 0], ['onBrandProductsChange', 0]];

        return $subscribed;
    }

    public function onModelWrite(object $event, string $eventName): void
    {
        $this->safely(function () use ($event, $eventName): void {
            $id = $event->getModel()->getId();

            if (null !== $id) {
                $this->queue->add(self::FAMILIES[$eventName], (int) $id);
            }
        });
    }

    public function onProductPreSave(ProductEvent $event): void
    {
        $this->safely(function () use ($event): void {
            $product = $event->getModel();

            if ($product instanceof Product && ($product->isNew() || self::anyColumnModified($product, self::INDEXED_PRODUCT_COLUMNS))) {
                $this->productsToIndex[$product] = true;
            }
        });
    }

    public function onProductPostSave(ProductEvent $event): void
    {
        $this->safely(function () use ($event): void {
            $product = $event->getModel();

            if (!$product instanceof Product || !isset($this->productsToIndex[$product])) {
                return;
            }

            unset($this->productsToIndex[$product]);

            if (null !== $product->getId()) {
                $this->queue->add(ProductDocumentProvider::FAMILY, (int) $product->getId());
            }
        });
    }

    public function onSaleElementsPreSave(ProductSaleElementsEvent $event): void
    {
        $saleElements = $event->getModel();

        if ($saleElements instanceof ProductSaleElements && ($saleElements->isNew() || self::anyColumnModified($saleElements, self::INDEXED_SALE_ELEMENTS_COLUMNS))) {
            $this->onSaleElementsWrite($event);
        }
    }

    public function onBrandTranslationPreSave(BrandI18nEvent $event): void
    {
        $translation = $event->getModel();

        if ($translation instanceof BrandI18n && ($translation->isNew() || $translation->isColumnModified(BrandI18nTableMap::COL_TITLE))) {
            $this->onBrandProductsChange($event);
        }
    }

    public function onSaleElementsWrite(ProductSaleElementsEvent $event): void
    {
        $this->safely(function () use ($event): void {
            $saleElements = $event->getModel();

            if ($saleElements instanceof ProductSaleElements && null !== $saleElements->getProductId()) {
                $this->queue->add(ProductDocumentProvider::FAMILY, (int) $saleElements->getProductId());
            }
        });
    }

    public function onBrandProductsChange(BrandEvent|BrandI18nEvent $event): void
    {
        $this->safely(function () use ($event): void {
            $brand = $event->getModel();
            $brandId = $brand instanceof Brand || $brand instanceof BrandI18n ? $brand->getId() : null;

            if (null === $brandId) {
                return;
            }

            /** @var list<int|string> $productIds */
            $productIds = ProductQuery::create()->filterByBrandId($brandId)->select(['Id'])->find()->getData();

            foreach ($productIds as $productId) {
                $this->queue->add(ProductDocumentProvider::FAMILY, (int) $productId);
            }
        });
    }

    public function flush(): void
    {
        $this->queue->flush();
    }

    /**
     * @param list<string> $columns
     */
    private static function anyColumnModified(Product|ProductSaleElements $model, array $columns): bool
    {
        foreach ($columns as $column) {
            if ($model->isColumnModified($column)) {
                return true;
            }
        }

        return false;
    }

    private function safely(\Closure $collect): void
    {
        try {
            if (!$this->engines->hasEngine()) {
                return;
            }

            $collect();
        } catch (\Throwable $exception) {
            $this->logger->error('Search indexing could not read a catalogue change: {exception}.', ['exception' => $exception::class]);
        }
    }
}
