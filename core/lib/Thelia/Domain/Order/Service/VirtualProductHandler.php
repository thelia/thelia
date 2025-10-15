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

namespace Thelia\Domain\Order\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Product\VirtualProductOrderHandleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Order as ModelOrder;
use Thelia\Model\Product;

readonly class VirtualProductHandler
{
    public function resolve(
        EventDispatcherInterface $dispatcher,
        ModelOrder $order,
        Product $product,
        int $productSaleElementsId,
    ): VirtualProductContext {
        $useStock = true;
        $isVirtual = false;
        $virtualDocumentPath = null;

        if (1 === $product->getVirtual()) {
            $event = new VirtualProductOrderHandleEvent($order, $productSaleElementsId);
            $dispatcher->dispatch($event, TheliaEvents::VIRTUAL_PRODUCT_ORDER_HANDLE);

            $useStock = $event->isUseStock();
            $isVirtual = $event->isVirtual();
            $virtualDocumentPath = $event->getPath();
        }

        return new VirtualProductContext($useStock, $isVirtual, $virtualDocumentPath);
    }
}
