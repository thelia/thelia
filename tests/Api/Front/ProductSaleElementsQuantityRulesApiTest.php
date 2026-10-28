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

namespace Thelia\Tests\Api\Front;

use Thelia\Test\ApiTestCase;

/**
 * A storefront reads the quantity rules and the unit of a combination to draw its
 * quantity selector and its reference price.
 */
final class ProductSaleElementsQuantityRulesApiTest extends ApiTestCase
{
    public function testTheFrontReadOfACombinationCarriesItsQuantityRulesAndItsUnit(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['basePrice' => 9.99]);
        $pse = $factory->productSaleElement($product, [
            'ref' => 'BOT-075',
            'quantity' => 37.5,
            'minQuantity' => 2,
            'quantityStep' => 6,
            'unitQuantity' => 0.75,
            'referenceUnitQuantity' => 1,
            'unitCode' => 'l',
        ]);

        $response = $this->jsonRequest('GET', '/api/front/product_sale_elements/'.$pse->getId());

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $json = json_decode((string) $response->getContent(), true);
        self::assertSame('BOT-075', $json['ref']);
        self::assertSame(37.5, $json['quantity']);
        self::assertEquals(2, $json['minQuantity']);
        self::assertEquals(6, $json['quantityStep']);
        self::assertSame(0, $json['quantityDecimals']);
        self::assertEquals(0.75, $json['unitQuantity']);
        self::assertEquals(1, $json['referenceUnitQuantity']);
        self::assertSame('l', $json['unitCode']);
    }

    public function testACombinationWithoutRulesReadsAsSoldByThePieceFromOne(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $pse = $factory->productSaleElement($product);

        $json = json_decode((string) $this->jsonRequest('GET', '/api/front/product_sale_elements/'.$pse->getId())->getContent(), true);

        self::assertEquals(1, $json['minQuantity']);
        self::assertEquals(1, $json['quantityStep']);
        self::assertSame(0, $json['quantityDecimals']);
        self::assertNull($json['unitQuantity'] ?? null);
        self::assertNull($json['referenceUnitQuantity'] ?? null);
        self::assertNull($json['unitCode'] ?? null);
    }
}
