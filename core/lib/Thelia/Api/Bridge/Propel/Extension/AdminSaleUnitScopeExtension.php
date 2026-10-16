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
use Thelia\Api\Resource\Cart;
use Thelia\Api\Resource\CartItem;
use Thelia\Api\Resource\Order;
use Thelia\Api\Resource\OrderAddress;
use Thelia\Api\Resource\OrderCoupon;
use Thelia\Api\Resource\OrderProduct;
use Thelia\Api\Resource\OrderProductTax;
use Thelia\Api\Resource\OrderReturn;
use Thelia\Api\Resource\OrderReturnLine;
use Thelia\Api\Resource\OrderShipment;
use Thelia\Api\Resource\OrderShipmentItem;
use Thelia\Api\Resource\SaleUnit;
use Thelia\Api\Resource\SaleUnitProductPrice;
use Thelia\Api\Resource\SaleUnitProductSaleElements;
use Thelia\Domain\SaleUnit\Access\AdminSaleUnitScope;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\OrderAddressTableMap;
use Thelia\Model\Map\OrderCouponTableMap;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderProductTaxTableMap;
use Thelia\Model\Map\OrderReturnLineTableMap;
use Thelia\Model\Map\OrderReturnTableMap;
use Thelia\Model\Map\OrderShipmentItemTableMap;
use Thelia\Model\Map\OrderShipmentTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Map\SaleUnitProductPriceTableMap;
use Thelia\Model\Map\SaleUnitProductSaleElementsTableMap;
use Thelia\Model\Map\SaleUnitTableMap;

/**
 * Narrows the admin API of a restricted administrator to its sale units: the
 * orders placed on them and everything that belongs to those orders (lines and
 * their taxes, addresses, coupons, shipments and their lines, returns and their
 * lines), the carts of those units and their lines, their prices, their stocks,
 * the units themselves. Collections lose the other rows, items answer 404, and
 * since a write loads its item first, an item out of reach cannot be changed or
 * deleted either.
 *
 * An administrator attached to no unit, and a superadministrator, see everything.
 * National orders and carts (no unit) belong to none of a restricted
 * administrator's units: they are left out.
 */
final readonly class AdminSaleUnitScopeExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    /** The column holding the sale unit of each resource. */
    private const array SALE_UNIT_COLUMNS = [
        Order::class => OrderTableMap::COL_SALE_UNIT_ID,
        Cart::class => CartTableMap::COL_SALE_UNIT_ID,
        SaleUnit::class => SaleUnitTableMap::COL_ID,
        SaleUnitProductPrice::class => SaleUnitProductPriceTableMap::COL_SALE_UNIT_ID,
        SaleUnitProductSaleElements::class => SaleUnitProductSaleElementsTableMap::COL_SALE_UNIT_ID,
    ];

    /**
     * The resources reached through another row: the column of the resource, and
     * the SELECT of the ids it may take, where %s stands for the allowed sale units.
     */
    private const array RELATED = [
        OrderProduct::class => [OrderProductTableMap::COL_ORDER_ID, self::ORDERS],
        OrderCoupon::class => [OrderCouponTableMap::COL_ORDER_ID, self::ORDERS],
        OrderShipment::class => [OrderShipmentTableMap::COL_ORDER_ID, self::ORDERS],
        OrderReturn::class => [OrderReturnTableMap::COL_ORDER_ID, self::ORDERS],
        OrderAddress::class => [OrderAddressTableMap::COL_ID, 'SELECT `order`.`invoice_order_address_id` FROM `order` WHERE `order`.`sale_unit_id` IN (%1$s) UNION SELECT `order`.`delivery_order_address_id` FROM `order` WHERE `order`.`sale_unit_id` IN (%1$s)'],
        OrderProductTax::class => [OrderProductTaxTableMap::COL_ORDER_PRODUCT_ID, 'SELECT `order_product`.`id` FROM `order_product` INNER JOIN `order` ON `order`.`id` = `order_product`.`order_id` WHERE `order`.`sale_unit_id` IN (%1$s)'],
        OrderShipmentItem::class => [OrderShipmentItemTableMap::COL_ORDER_SHIPMENT_ID, 'SELECT `order_shipment`.`id` FROM `order_shipment` INNER JOIN `order` ON `order`.`id` = `order_shipment`.`order_id` WHERE `order`.`sale_unit_id` IN (%1$s)'],
        OrderReturnLine::class => [OrderReturnLineTableMap::COL_ORDER_RETURN_ID, 'SELECT `order_return`.`id` FROM `order_return` INNER JOIN `order` ON `order`.`id` = `order_return`.`order_id` WHERE `order`.`sale_unit_id` IN (%1$s)'],
        CartItem::class => [CartItemTableMap::COL_CART_ID, 'SELECT `cart`.`id` FROM `cart` WHERE `cart`.`sale_unit_id` IN (%1$s)'],
    ];

    private const string ORDERS = 'SELECT `order`.`id` FROM `order` WHERE `order`.`sale_unit_id` IN (%1$s)';

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
        if (!isset(self::SALE_UNIT_COLUMNS[$resourceClass]) && !isset(self::RELATED[$resourceClass])) {
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

        [$column, $select] = self::RELATED[$resourceClass];

        // The ids are integers read from the database, never from the request.
        $query->addAnd($column, \sprintf(
            '%s IN (%s)',
            $column,
            \sprintf($select, implode(',', array_map('intval', $allowed))),
        ), Criteria::CUSTOM);
    }
}
