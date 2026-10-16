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

namespace Thelia\Tests\Api\Admin;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Domain\Shipping\DTO\ShipmentLine;
use Thelia\Domain\Shipping\Service\OrderShipmentManager;
use Thelia\Model\Admin;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Map\SaleUnitTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderShipment;
use Thelia\Model\OrderStatus;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\SaleUnit;
use Thelia\Model\SaleUnitProductSaleElementsQuery;
use Thelia\Model\SaleUnitQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * An administrator restricted to some sale units reaches, through the admin API,
 * their orders (and the lines of those orders), their prices, their stocks and
 * the units themselves, and nothing of the others nor any national order: a
 * collection leaves them out, an item answers 404, a write for another unit is
 * refused. An administrator attached to no unit, and a superadministrator, reach
 * everything as before.
 */
final class SaleUnitAdminRestrictionApiTest extends ApiTestCase
{
    private const array ALL = [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE];

    private FixtureFactory $factory;

    private Currency $currency;

    private SaleUnit $mine;

    private SaleUnit $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->currency = $this->factory->currency();
        $this->mine = $this->factory->saleUnit();
        $this->theirs = $this->factory->saleUnit();
    }

    public function testARestrictedAdministratorListsTheOrdersOfItsUnitsOnly(): void
    {
        $mine = $this->orderOn($this->mine);
        $theirs = $this->orderOn($this->theirs);
        $national = $this->orderOn(null);
        $token = $this->authenticateAsAdmin($this->restrictedTo($this->mine));

        $ids = $this->ids($this->jsonRequest('GET', '/api/admin/orders?itemsPerPage=1000', token: $token));

        self::assertContains($mine->getId(), $ids);
        self::assertNotContains($theirs->getId(), $ids);
        self::assertNotContains($national->getId(), $ids, 'A national order belongs to none of its units.');

        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/orders/'.$mine->getId(), token: $token));
        self::assertSame(404, $this->jsonRequest('GET', '/api/admin/orders/'.$theirs->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/admin/orders/'.$national->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('PATCH', '/api/admin/orders/'.$theirs->getId(), ['invoiceRef' => 'X'], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(404, $this->jsonRequest('DELETE', '/api/admin/orders/'.$theirs->getId(), token: $token)->getStatusCode());
    }

    public function testTheLinesOfAnOrderOfAnotherUnitAreOutOfReach(): void
    {
        $mine = $this->factory->orderProduct($this->orderOn($this->mine));
        $theirs = $this->factory->orderProduct($this->orderOn($this->theirs));
        $token = $this->authenticateAsAdmin($this->restrictedTo($this->mine));

        $ids = $this->ids($this->jsonRequest('GET', '/api/admin/order_products?itemsPerPage=1000', token: $token));

        self::assertContains($mine->getId(), $ids);
        self::assertNotContains($theirs->getId(), $ids);
        self::assertSame(404, $this->jsonRequest('GET', '/api/admin/order_products/'.$theirs->getId(), token: $token)->getStatusCode());
        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/orders/'.$mine->getOrderId().'/history', token: $token));
        self::assertSame(404, $this->jsonRequest('GET', '/api/admin/orders/'.$theirs->getOrderId().'/history', token: $token)->getStatusCode());
    }

    public function testARestrictedAdministratorReachesThePricesAndStocksOfItsUnitsOnly(): void
    {
        $productSaleElements = $this->productSaleElements();
        $myStock = $this->factory->saleUnitProductSaleElements($this->mine, $productSaleElements, 3.0);
        $theirStock = $this->factory->saleUnitProductSaleElements($this->theirs, $productSaleElements, 9.0);
        $theirPrice = $this->factory->saleUnitProductPrice($this->theirs, $productSaleElements, $this->currency, 5.0);
        $token = $this->authenticateAsAdmin($this->restrictedTo($this->mine));

        self::assertSame([$myStock->getId()], $this->ids($this->jsonRequest('GET', '/api/admin/sale_unit_product_sale_elements?productSaleElements.id='.$productSaleElements->getId(), token: $token)));
        self::assertSame([], $this->ids($this->jsonRequest('GET', '/api/admin/sale_unit_product_prices?productSaleElements.id='.$productSaleElements->getId(), token: $token)));
        self::assertSame(404, $this->jsonRequest('GET', '/api/admin/sale_unit_product_sale_elements/'.$theirStock->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('PATCH', '/api/admin/sale_unit_product_sale_elements/'.$theirStock->getId(), ['quantity' => 1], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(404, $this->jsonRequest('DELETE', '/api/admin/sale_unit_product_prices/'.$theirPrice->getId(), token: $token)->getStatusCode());

        $changed = $this->jsonRequest('PATCH', '/api/admin/sale_unit_product_sale_elements/'.$myStock->getId(), ['quantity' => 4], $token, 'merge-patch+json');
        self::assertJsonResponseSuccessful($changed);
    }

    public function testARestrictedAdministratorCannotWriteForAnotherUnit(): void
    {
        $productSaleElements = $this->productSaleElements();
        $myStock = $this->factory->saleUnitProductSaleElements($this->mine, $productSaleElements, 3.0);
        $token = $this->authenticateAsAdmin($this->restrictedTo($this->mine));

        // Another unit is out of this administrator's reach: its IRI resolves to
        // nothing, the way an IRI naming no unit at all does.
        $refused = $this->jsonRequest('POST', '/api/admin/sale_unit_product_prices', [
            'saleUnit' => '/api/admin/sale_units/'.$this->theirs->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$productSaleElements->getId(),
            'currency' => '/api/admin/currencies/'.$this->currency->getId(),
            'price' => 12.5,
            'promoPrice' => 10.0,
        ], $token);
        self::assertSame(400, $refused->getStatusCode());
        self::assertStringContainsString('Item not found', (string) $refused->getContent());

        self::assertSame(400, $this->jsonRequest('PATCH', '/api/admin/sale_unit_product_sale_elements/'.$myStock->getId(), [
            'saleUnit' => '/api/admin/sale_units/'.$this->theirs->getId(),
        ], $token, 'merge-patch+json')->getStatusCode(), 'A stock cannot be moved to another unit.');

        self::assertSame(
            $this->mine->getId(),
            SaleUnitProductSaleElementsQuery::create()->findPk($myStock->getId(), $this->getPropelConnection())?->getSaleUnitId(),
        );

        $created = $this->jsonRequest('POST', '/api/admin/sale_unit_product_prices', [
            'saleUnit' => '/api/admin/sale_units/'.$this->mine->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$productSaleElements->getId(),
            'currency' => '/api/admin/currencies/'.$this->currency->getId(),
            'price' => 12.5,
            'promoPrice' => 10.0,
        ], $token);
        self::assertJsonResponseSuccessful($created);
    }

    public function testARestrictedAdministratorSeesItsUnitsAndCreatesNone(): void
    {
        $token = $this->authenticateAsAdmin($this->restrictedTo($this->mine));

        self::assertSame([$this->mine->getId()], $this->ids($this->jsonRequest('GET', '/api/admin/sale_units?itemsPerPage=1000', token: $token)));
        self::assertSame(404, $this->jsonRequest('PATCH', '/api/admin/sale_units/'.$this->theirs->getId(), ['active' => false], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/sale_units', ['code' => 'another-unit', 'active' => false], $token)->getStatusCode());
    }

    public function testAnAdministratorAttachedToNoUnitAndASuperadministratorReachEverything(): void
    {
        $theirs = $this->orderOn($this->theirs);
        $national = $this->orderOn(null);
        $superadmin = $this->factory->admin();
        $this->factory->adminSaleUnit($superadmin, $this->mine);

        foreach ([$this->profiledAdmin(), $superadmin] as $admin) {
            $token = $this->authenticateAsAdmin($admin);

            self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/orders/'.$theirs->getId(), token: $token));
            self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/orders/'.$national->getId(), token: $token));
            self::assertContains($this->theirs->getId(), $this->ids($this->jsonRequest('GET', '/api/admin/sale_units?itemsPerPage=1000', token: $token)));
        }
    }

    public function testAUnitStillAttachedToAnAdministratorCannotBeDeleted(): void
    {
        $theirs = $this->orderOn($this->theirs);
        $admin = $this->restrictedTo($this->mine);

        // Deleting the only unit of a restricted administrator would leave it with
        // no row, which reads as "every unit": the deletion is refused instead.
        $refused = $this->jsonRequest('DELETE', '/api/admin/sale_units/'.$this->mine->getId(), token: $this->authenticateAsAdmin());
        self::assertSame(409, $refused->getStatusCode(), (string) $refused->getContent());
        self::assertStringContainsString('administrator', (string) $refused->getContent());

        SaleUnitTableMap::clearInstancePool();
        self::assertNotNull(SaleUnitQuery::create()->findPk($this->mine->getId(), $this->getPropelConnection()));
        self::assertSame(404, $this->jsonRequest('GET', '/api/admin/orders/'.$theirs->getId(), token: $this->authenticateAsAdmin($admin))->getStatusCode());
    }

    public function testARestrictedAdministratorDeletesNoUnit(): void
    {
        $token = $this->authenticateAsAdmin($this->restrictedTo($this->mine));

        self::assertSame(403, $this->jsonRequest('DELETE', '/api/admin/sale_units/'.$this->mine->getId(), token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/sale_units', ['code' => 'another-unit', 'active' => false], $token)->getStatusCode());
    }

    public function testTheTransitionsOfAnotherUnitAreOutOfReach(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');
        $order = $this->paidOrderOn($this->theirs);
        $line = $this->factory->orderProduct($order, ['quantity' => 2.0]);
        $manager = $this->getService(OrderShipmentManager::class);
        $draft = $manager->open($order, [new ShipmentLine((int) $line->getId(), 1.0)]);
        $return = $this->returnOf($order, $line);
        $token = $this->authenticateAsAdmin($this->restrictedTo($this->mine));

        $shipment = $this->jsonRequest('POST', '/api/admin/order_shipments/'.$draft->getId().'/transition', ['action' => 'cancel'], token: $token);
        self::assertSame(404, $shipment->getStatusCode(), (string) $shipment->getContent());
        $draft->reload(false, $this->getPropelConnection());
        self::assertSame(OrderShipment::STATUS_DRAFT, $draft->getStatus());

        $returned = $this->jsonRequest('POST', '/api/admin/order_returns/'.$return->getId().'/transition', ['statusCode' => OrderReturnStatus::CODE_RECEIVED], token: $token);
        self::assertSame(404, $returned->getStatusCode(), (string) $returned->getContent());
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();
    }

    public function testAParcelOrAReturnCannotBeOpenedOnAnOrderOfAnotherUnit(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');
        $order = $this->paidOrderOn($this->theirs);
        $line = $this->factory->orderProduct($order, ['quantity' => 2.0]);
        $token = $this->authenticateAsAdmin($this->restrictedTo($this->mine));

        $parcel = $this->jsonRequest('POST', '/api/admin/order_shipments', [
            'order' => '/api/admin/orders/'.$order->getId(),
            'orderShipmentItems' => [['orderProduct' => '/api/admin/order_products/'.$line->getId(), 'quantity' => 1]],
        ], token: $token);
        self::assertContains($parcel->getStatusCode(), [400, 404, 422], (string) $parcel->getContent());

        $return = $this->jsonRequest('POST', '/api/admin/order_returns', [
            'order' => '/api/admin/orders/'.$order->getId(),
            'orderReturnLines' => [['orderProduct' => '/api/admin/order_products/'.$line->getId(), 'quantity' => 1.0]],
        ], token: $token);
        self::assertContains($return->getStatusCode(), [400, 404, 422], (string) $return->getContent());

        self::assertSame(0, $order->countOrderShipments(null, false, $this->getPropelConnection()));
        self::assertSame(0, $order->countOrderReturns(null, false, $this->getPropelConnection()));
        ConfigQuery::resetCache();
    }

    public function testTheDeeperChildrenOfAnOrderOfAnotherUnitAreOutOfReach(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');
        $mine = $this->paidOrderOn($this->mine);
        $theirs = $this->paidOrderOn($this->theirs);
        $children = [];

        foreach (['mine' => $mine, 'theirs' => $theirs] as $key => $order) {
            $line = $this->factory->orderProduct($order, ['quantity' => 2.0]);
            $tax = (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT')->setAmount('2.000000')->setPromoAmount('0.000000');
            $tax->save($this->getPropelConnection());
            $parcel = $this->getService(OrderShipmentManager::class)->open($order, [new ShipmentLine((int) $line->getId(), 1.0)]);
            $return = $this->returnOf($order, $line);
            $children[$key] = [
                'order_addresses' => $order->getInvoiceOrderAddressId(),
                'order_product_taxes' => $tax->getId(),
                'order_shipment_items' => $parcel->getOrderShipmentItems()->getFirst()?->getId(),
                'order_return_lines' => $return->getOrderReturnLines()->getFirst()?->getId(),
            ];
        }

        $mineCart = $this->cartOn($this->mine);
        $theirCart = $this->cartOn($this->theirs);
        $children['mine']['carts'] = $mineCart->getId();
        $children['theirs']['carts'] = $theirCart->getId();
        $children['mine']['cart_items'] = $this->cartItemOf($mineCart)->getId();
        $children['theirs']['cart_items'] = $this->cartItemOf($theirCart)->getId();

        $token = $this->authenticateAsAdmin($this->restrictedTo($this->mine));

        foreach ($children['theirs'] as $resource => $id) {
            self::assertNotNull($id, $resource);
            self::assertSame(404, $this->jsonRequest('GET', '/api/admin/'.$resource.'/'.$id, token: $token)->getStatusCode(), $resource);
            self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/'.$resource.'/'.$children['mine'][$resource], token: $token));
            self::assertNotContains($id, $this->ids($this->jsonRequest('GET', '/api/admin/'.$resource.'?itemsPerPage=100&order[id]=desc', token: $token)), $resource);
        }

        ConfigQuery::resetCache();
    }

    private function paidOrderOn(SaleUnit $saleUnit): Order
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $order->setSaleUnitId($saleUnit->getId())->save($this->getPropelConnection());

        return $order;
    }

    private function returnOf(Order $order, OrderProduct $line): OrderReturn
    {
        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomerId($order->getCustomerId())
            ->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_ACCEPTED));
        $return->save($this->getPropelConnection());
        (new OrderReturnLine())
            ->setOrderReturn($return)
            ->setOrderProduct($line)
            ->setProductSaleElementsId($line->getProductSaleElementsId())
            ->setQuantity(1.0)
            ->setQuantityReceived(0.0)
            ->save($this->getPropelConnection());
        $return->clearOrderReturnLines();

        return $return;
    }

    private function cartOn(SaleUnit $saleUnit): \Thelia\Model\Cart
    {
        $cart = $this->factory->cart(null, ['currency' => $this->currency]);
        $cart->setSaleUnitId($saleUnit->getId())->save($this->getPropelConnection());

        return $cart;
    }

    private function cartItemOf(\Thelia\Model\Cart $cart): \Thelia\Model\CartItem
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency);

        return $this->factory->cartItem($cart, $product, $product->getDefaultSaleElements());
    }

    private function profiledAdmin(): Admin
    {
        return $this->factory->restrictedAdmin([
            AdminResources::ORDER => self::ALL,
            AdminResources::ORDER_RETURN => self::ALL,
            AdminResources::SALE_UNIT => self::ALL,
        ]);
    }

    private function restrictedTo(SaleUnit $saleUnit): Admin
    {
        $admin = $this->profiledAdmin();
        $this->factory->adminSaleUnit($admin, $saleUnit);

        return $admin;
    }

    private function orderOn(?SaleUnit $saleUnit): Order
    {
        $order = $this->factory->order();
        $order->setSaleUnitId($saleUnit?->getId())->save($this->getPropelConnection());

        return $order;
    }

    private function productSaleElements(): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency);

        return $product->getDefaultSaleElements();
    }

    /**
     * @return list<int>
     */
    private function ids(Response $response): array
    {
        self::assertJsonResponseSuccessful($response);
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return array_map('intval', array_column($body['hydra:member'] ?? $body['member'] ?? [], 'id'));
    }
}
