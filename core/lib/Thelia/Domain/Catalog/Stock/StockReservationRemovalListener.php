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

namespace Thelia\Domain\Catalog\Stock;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Customer;
use Thelia\Model\Event\CustomerEvent;
use Thelia\Model\Event\ProductEvent;
use Thelia\Model\Event\ProductSaleElementsEvent;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;

/**
 * Cart lines that go with something else are told as released like any other removal: the lines
 * of a deleted product, sale element or customer (removed by the foreign keys of the database),
 * and the lines of a product that becomes a pack or stops being one (Product::postSave()).
 *
 * Read in the transaction of the write, before it, since nothing is left to read afterwards; told
 * once that transaction is over, for the lines that are gone (StockReservationAnnouncer). Every
 * writer is covered (the admin API, the back office, the actions), except a bulk delete of a query,
 * which reaches no hook. A shop that never reserved reads nothing.
 */
final readonly class StockReservationRemovalListener
{
    public function __construct(private StockReservationService $stockReservation)
    {
    }

    #[AsEventListener(event: ProductEvent::PRE_DELETE)]
    public function beforeProductDeletion(ProductEvent $event): void
    {
        $product = $event->getModel();

        if ($product instanceof Product) {
            $this->stockReservation->announceTheReleaseOfLinesAmong(CartItemQuery::create()->filterByProductId($product->getId()));
        }
    }

    #[AsEventListener(event: ProductEvent::PRE_UPDATE)]
    public function beforeProductChangesItsForm(ProductEvent $event): void
    {
        $product = $event->getModel();

        if ($product instanceof Product && $product->isColumnModified(ProductTableMap::COL_IS_BUNDLE)) {
            $this->stockReservation->announceTheReleaseOfLinesAmong(CartItemQuery::create()->filterByProductId($product->getId()));
        }
    }

    #[AsEventListener(event: ProductSaleElementsEvent::PRE_DELETE)]
    public function beforeSaleElementDeletion(ProductSaleElementsEvent $event): void
    {
        $productSaleElements = $event->getModel();

        if ($productSaleElements instanceof ProductSaleElements) {
            $this->stockReservation->announceTheReleaseOfLinesAmong(CartItemQuery::create()->filterByProductSaleElementsId($productSaleElements->getId()));
        }
    }

    #[AsEventListener(event: CustomerEvent::PRE_DELETE)]
    public function beforeCustomerDeletion(CustomerEvent $event): void
    {
        $customer = $event->getModel();

        if ($customer instanceof Customer) {
            $this->stockReservation->announceTheReleaseOfLinesAmong(CartItemQuery::create()->useCartQuery()->filterByCustomerId($customer->getId())->endUse());
        }
    }
}
