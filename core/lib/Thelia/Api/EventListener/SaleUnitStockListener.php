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
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Domain\SaleUnit\Stock\SaleUnitAvailableStock;

/**
 * Serves the stock of the sale unit the visitor buys from, on the front read of a
 * sale element, when the unit keeps a row of its own: the quantity a theme shows
 * and limits the quantity picker to is the one the checkout will decrement.
 *
 * A request that resolved no sale unit, every request of a single shop among them,
 * keeps the national quantity without a query. Admin reads are left alone: the
 * back office edits the national counter.
 */
readonly class SaleUnitStockListener implements EventSubscriberInterface
{
    public function __construct(private SaleUnitAvailableStock $saleUnitAvailableStock)
    {
    }

    public function applySaleUnitStock(ModelToResourceEvent $modelToResourceEvent): void
    {
        $resource = $modelToResourceEvent->getResource();

        if (!$resource instanceof ProductSaleElementsResource || null === $resource->getId()) {
            return;
        }

        if (!EffectivePriceListener::isFrontRead($modelToResourceEvent->getContext())) {
            return;
        }

        $quantity = $this->saleUnitAvailableStock->ownQuantity($resource->getId());

        if (null === $quantity) {
            return;
        }

        $resource->setQuantity((int) floor($quantity));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['applySaleUnitStock', -10],
            ],
        ];
    }
}
