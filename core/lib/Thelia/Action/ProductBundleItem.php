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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Product\ProductBundleItemEvent;
use Thelia\Core\Event\Product\ProductBundleItemPositionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;

/**
 * The components of a product bundle, written by the events of the back office through the
 * same service as the API, so the two refuse the same things.
 */
final class ProductBundleItem extends BaseAction implements EventSubscriberInterface
{
    public function __construct(private readonly BundleCompositionService $bundleCompositionService)
    {
    }

    public function add(ProductBundleItemEvent $event): void
    {
        $event->setItem($this->bundleCompositionService->addItem(
            (int) $event->getBundleProductId(),
            (int) $event->getProductSaleElementsId(),
            $event->getQuantity(),
            $event->getMinQuantity(),
            $event->getMaxQuantity(),
            $event->isRequired(),
            $event->getPosition(),
        ));
    }

    public function update(ProductBundleItemEvent $event): void
    {
        $event->setItem($this->bundleCompositionService->updateItem(
            (int) $event->getBundleItemId(),
            (int) $event->getProductSaleElementsId(),
            $event->getQuantity(),
            $event->getMinQuantity(),
            $event->getMaxQuantity(),
            $event->isRequired(),
        ));
    }

    public function delete(ProductBundleItemEvent $event): void
    {
        $this->bundleCompositionService->removeItem((int) $event->getBundleItemId());
    }

    public function updatePosition(ProductBundleItemPositionEvent $event): void
    {
        $event->setItem($this->bundleCompositionService->updatePosition($event->getBundleItemId(), $event->getPosition()));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::PRODUCT_BUNDLE_ITEM_ADD => ['add', 128],
            TheliaEvents::PRODUCT_BUNDLE_ITEM_UPDATE => ['update', 128],
            TheliaEvents::PRODUCT_BUNDLE_ITEM_DELETE => ['delete', 128],
            TheliaEvents::PRODUCT_BUNDLE_ITEM_UPDATE_POSITION => ['updatePosition', 128],
        ];
    }
}
