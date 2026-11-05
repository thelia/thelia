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
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Api\Bridge\Propel\Event\CollectionModelsLoadedEvent;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Resource\Product as ProductResource;
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Catalog\Stock\VisitorSellableStock;
use Thelia\Model\Accessory;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Serves, on the front read of a sale element, how many units the visitor may still buy: the
 * stock they buy from plus what the sale mode of the sale element allows below zero, less what
 * other carts reserve (null: no limit). `quantity` stays the stock, this is the number a theme
 * limits a quantity picker to and tells "available" or "on order" by. `isPreorder` tells whether
 * the next unit bought would be sold below zero stock, the rule the checkout freezes on the line.
 *
 * It runs after the sale unit and the bundle listeners: a bundle is supplied by its components,
 * so it serves the number of bundles they allow, which those listeners have put in `quantity`.
 * Admin reads are left alone: the back office edits the stock, not what a visitor may buy.
 *
 * A page is answered once, when its models are loaded (the way {@see BundleStockListener} does):
 * the sale elements of the page, or of the products on it, are answered together, with one read of
 * the reservations whatever their number, and each member is answered from memory. A sale element
 * reached any other way is answered on its own; those of the products a block of associations points
 * at are answered with the block. What is kept lasts the request ({@see reset()}).
 */
final class AvailableQuantityListener implements EventSubscriberInterface, ResetInterface
{
    /** @var array<int, array{available: float|null, preorder: bool}> by sale element id */
    private array $answers = [];

    public function __construct(
        private readonly VisitorSellableStock $visitorSellableStock,
        private readonly StockSettings $stockSettings,
    ) {
    }

    public function warm(CollectionModelsLoadedEvent $event): void
    {
        if (!EffectivePriceListener::isFrontRead($event->getContext()) || !ConfigQuery::checkAvailableStock()) {
            return;
        }

        $saleElements = [];
        $products = [];
        $cartItems = [];
        /** @var array<int, Product> $associatedProducts */
        $associatedProducts = [];
        // A page of products is answered when it shows their sale elements, which it reads anyway.
        $showsSaleElements = [] !== array_intersect([ProductResource::GROUP_FRONT_READ, ProductResource::GROUP_FRONT_READ_SINGLE], (array) ($event->getContext()['groups'] ?? []));

        foreach ($event->getModels() as $model) {
            if ($model instanceof ProductSaleElements) {
                $saleElements[(int) $model->getId()] = $model;
            } elseif ($showsSaleElements && $model instanceof Product && 1 !== (int) $model->getIsBundle()) {
                $products[(int) $model->getId()] = $model;

                /** @var ProductSaleElements $saleElement */
                foreach ($model->getProductSaleElementss() as $saleElement) {
                    $saleElements[(int) $saleElement->getId()] = $saleElement;
                }
            } elseif ($model instanceof CartItem) {
                $cartItems[] = (int) $model->getProductSaleElementsId();
            } elseif ($model instanceof Accessory && $this->stockSettings->reservationEnabled()) {
                // Both ends of the relation are served with it, each with its sale elements.
                foreach ([$model->getProductRelatedByProductId(), $model->getProductRelatedByAccessory()] as $associated) {
                    if (1 !== (int) $associated->getIsBundle()) {
                        $associatedProducts[(int) $associated->getId()] = $associated;
                    }
                }
            }
        }

        // The products of a block of associations, read with the block (preloaded): their sale
        // elements ask for a read of the reservations each, which a shop that reserves nothing never
        // does. When it does, they are read together, one query for the block.
        if ([] !== $associatedProducts) {
            /** @var ProductSaleElements $saleElement */
            foreach (ProductSaleElementsQuery::create()->filterByProductId(array_keys($associatedProducts), Criteria::IN)->find() as $saleElement) {
                $saleElements[(int) $saleElement->getId()] = $saleElement;
            }

            $products += $associatedProducts;
        }

        // The lines of a cart ask for a read of the reservations each, which a shop that reserves nothing never
        // does: their sale elements are read together only when it does.
        if ([] !== $cartItems && $this->stockSettings->reservationEnabled()) {
            /** @var ProductSaleElements $saleElement */
            foreach (ProductSaleElementsQuery::create()->filterById(array_values(array_unique($cartItems)), Criteria::IN)->find() as $saleElement) {
                $saleElements[(int) $saleElement->getId()] = $saleElement;
            }
        }

        if ([] === $saleElements) {
            return;
        }

        $this->answers = $this->visitorSellableStock->forMany($saleElements, true, $products) + $this->answers;
    }

    public function applyAvailableQuantity(ModelToResourceEvent $modelToResourceEvent): void
    {
        $resource = $modelToResourceEvent->getResource();

        if (!$resource instanceof ProductSaleElementsResource) {
            return;
        }

        // The event carries the model the whole read started from (the product, for a sale element
        // nested in it): the one this resource was built from is the one it knows.
        $model = $resource->getPropelModel();

        if (!$model instanceof ProductSaleElements) {
            return;
        }

        if (!EffectivePriceListener::isFrontRead($modelToResourceEvent->getContext())) {
            return;
        }

        // Read when the page was loaded; a bundle is never in it.
        $answer = $this->answers[(int) $model->getId()] ?? null;

        if (null !== $answer) {
            $resource->availableQuantity = $answer['available'];
            $resource->isPreorder = $answer['preorder'];

            return;
        }

        if (1 === (int) $model->getProduct()->getIsBundle()) {
            $resource->availableQuantity = ConfigQuery::checkAvailableStock() ? (float) $resource->getQuantity() : null;
            $resource->isPreorder = false;

            return;
        }

        $alone = $this->visitorSellableStock->forMany([$model])[(int) $model->getId()] ?? null;
        $resource->availableQuantity = $alone['available'] ?? null;
        $resource->isPreorder = $alone['preorder'] ?? false;

        // Kept for the request: the same sale element met again (the product every relation of a
        // block starts from) is not read again.
        if (null !== $alone) {
            $this->answers[(int) $model->getId()] = $alone;
        }
    }

    public function reset(): void
    {
        $this->answers = [];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CollectionModelsLoadedEvent::class => 'warm',
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['applyAvailableQuantity', -30],
            ],
        ];
    }
}
