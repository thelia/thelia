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

use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Test\ApiTestCase;

/**
 * An order line returns the price it was taxed at, and that figure is a sum of
 * its tax rows. The rows themselves belong to the single read, the sum to the
 * collections too: the computation has to reach the taxes without depending on
 * the relation the serializer hydrated for the payload.
 */
final class OrderProductUnitTaxedPriceApiTest extends ApiTestCase
{
    private const UNIT_PRICE = 12.5;

    private const UNIT_TAX = 2.25;

    public function testTheCollectionReturnsTheUnitTaxedPrice(): void
    {
        $orderProduct = $this->taxedOrderLine();
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest(
            'GET',
            '/api/admin/order_products?id='.$orderProduct->getId(),
            token: $token,
        );

        self::assertJsonResponseSuccessful($response);
        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $member = $payload['hydra:member'][0] ?? null;

        self::assertNotNull($member, 'The collection must return the order line under test.');
        self::assertArrayNotHasKey('orderProductTaxes', $member);
        self::assertSame(self::UNIT_PRICE + self::UNIT_TAX, $member['unitTaxedPrice']);
    }

    public function testTheSingleReadReturnsBothTheTaxesAndTheUnitTaxedPrice(): void
    {
        $orderProduct = $this->taxedOrderLine();
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest(
            'GET',
            '/api/admin/order_products/'.$orderProduct->getId(),
            token: $token,
        );

        self::assertJsonResponseSuccessful($response);
        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertCount(1, $payload['orderProductTaxes'] ?? []);
        self::assertSame(self::UNIT_PRICE + self::UNIT_TAX, $payload['unitTaxedPrice']);
    }

    private function taxedOrderLine(): OrderProduct
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();
        $order = $factory->order();

        $orderProduct = new OrderProduct();
        $orderProduct->setOrderId($order->getId());
        $orderProduct->setProductRef('ORDER-PROD-REF');
        $orderProduct->setProductSaleElementsRef('ORDER-PSE-REF');
        $orderProduct->setTitle('Taxed line');
        $orderProduct->setQuantity(1.0);
        $orderProduct->setPrice((string) self::UNIT_PRICE);
        $orderProduct->setWasNew(0);
        $orderProduct->setWasInPromo(0);
        $orderProduct->setVirtual(0);
        $orderProduct->save($connection);

        $tax = new OrderProductTax();
        $tax->setOrderProductId($orderProduct->getId());
        $tax->setTitle('VAT');
        $tax->setAmount((string) self::UNIT_TAX);
        $tax->save($connection);

        return $orderProduct;
    }
}
