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

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Resource\Order;
use Thelia\Api\Resource\OrderCoupon;
use Thelia\Api\Resource\OrderProduct;
use Thelia\Api\Resource\OrderReturn;
use Thelia\Api\Resource\OrderShipment;
use Thelia\Api\Resource\SaleUnit;
use Thelia\Api\Resource\SaleUnitProductPrice;
use Thelia\Api\Resource\SaleUnitProductSaleElements;
use Thelia\Domain\SaleUnit\Access\AdminSaleUnitScope;
use Thelia\Model\Map\OrderCouponTableMap;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderReturnTableMap;
use Thelia\Model\Map\OrderShipmentTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Map\SaleUnitProductPriceTableMap;
use Thelia\Model\Map\SaleUnitProductSaleElementsTableMap;
use Thelia\Model\Map\SaleUnitTableMap;

/**
 * Narrows the admin API of a restricted administrator to its sale units: the
 * orders placed on them and the lines, coupons, shipments and returns of those
 * orders, their prices, their stocks, the units themselves. Collections lose the
 * other rows, items answer 404, and since a write loads its item first, an item
 * out of reach cannot be changed or deleted either.
 *
 * An administrator attached to no unit, and a superadministrator, see everything.
 * National orders (placed on no unit) belong to none of a restricted
 * administrator's units: they are left out.
 */
final readonly class AdminSaleUnitScopeExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    /** The column holding the sale unit of each resource. */
    private const array SALE_UNIT_COLUMNS = [
        Order::class => OrderTableMap::COL_SALE_UNIT_ID,
        SaleUnit::class => SaleUnitTableMap::COL_ID,
        SaleUnitProductPrice::class => SaleUnitProductPriceTableMap::COL_SALE_UNIT_ID,
        SaleUnitProductSaleElements::class => SaleUnitProductSaleElementsTableMap::COL_SALE_UNIT_ID,
    ];

    /** The column holding the order of each resource that belongs to an order. */
    private const array ORDER_COLUMNS = [
        OrderProduct::class => OrderProductTableMap::COL_ORDER_ID,
        OrderCoupon::class => OrderCouponTableMap::COL_ORDER_ID,
        OrderShipment::class => OrderShipmentTableMap::COL_ORDER_ID,
        OrderReturn::class => OrderReturnTableMap::COL_ORDER_ID,
    ];

    public function __construct(private AdminSaleUnitScope $scope)
    {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->restrict($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->restrict($query, $resourceClass, $operation);
    }

    private function restrict(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (!isset(self::SALE_UNIT_COLUMNS[$resourceClass]) && !isset(self::ORDER_COLUMNS[$resourceClass])) {
            return;
        }

        if (!$operation instanceof HttpOperation || !str_starts_with($operation->getUriTemplate() ?? '', '/admin')) {
            return;
        }

        $allowed = $this->scope->allowedSaleUnitIds();

        if (null === $allowed) {
            return;
        }

        if (isset(self::SALE_UNIT_COLUMNS[$resourceClass])) {
            $query->addAnd(self::SALE_UNIT_COLUMNS[$resourceClass], $allowed, Criteria::IN);

            return;
        }

        // The ids are integers read from the database, never from the request.
        $column = self::ORDER_COLUMNS[$resourceClass];
        $query->addAnd($column, \sprintf(
            '%s IN (SELECT `order`.`id` FROM `order` WHERE `order`.`sale_unit_id` IN (%s))',
            $column,
            implode(',', array_map('intval', $allowed)),
        ), Criteria::CUSTOM);
    }
}
