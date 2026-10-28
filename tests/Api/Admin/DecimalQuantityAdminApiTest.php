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

use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\OrderProduct;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The admin API reads the unit and the decimals an order line was sold with, judges a
 * stock on the decimals of its combination, and never writes the quantity of a cart line
 * around the rules of its combination.
 */
final class DecimalQuantityAdminApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
    }

    public function testTheOrderLineGivesTheUnitAndTheDecimalsItWasSoldWith(): void
    {
        $order = $this->factory->order();
        $orderProduct = (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('WEIGHED')
            ->setProductSaleElementsRef('WEIGHED-PSE')
            ->setTitle('Weighed product')
            ->setQuantity(1.25)
            ->setUnitCode('kg')
            ->setQuantityDecimals(2)
            ->setPrice('12.350000')
            ->setWasNew(0)
            ->setWasInPromo(0);
        $orderProduct->save($this->getPropelConnection());

        $read = self::decodeJson($this->jsonRequest('GET', '/api/admin/order_products/'.$orderProduct->getId(), token: $this->authenticateAsAdmin()));

        self::assertSame(1.25, $read['quantity']);
        self::assertSame('kg', $read['unitCode']);
        self::assertSame(2, $read['quantityDecimals']);
    }

    public function testAStockWithMoreDecimalsThanTheCombinationIsRefused(): void
    {
        $combination = $this->combination(new QuantityRule());

        $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$combination->getId(), ['quantity' => 12.5], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertContains('quantity', array_column(self::decodeJson($response)['violations'] ?? [], 'propertyPath'));
        self::assertEqualsWithDelta(10.0, (float) ProductSaleElementsQuery::create()->findPk($combination->getId())?->getQuantity(), 0.0001);
    }

    public function testAStockOffTheStepIsAStockAllTheSame(): void
    {
        $combination = $this->combination(new QuantityRule(minQuantity: 2.0, step: 6.0));

        $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$combination->getId(), ['quantity' => 7], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertSame(7.0, (float) self::decodeJson($response)['quantity']);
    }

    /**
     * The admin cart line resource writes nothing into the line: a quantity sent there
     * cannot go around the rules of the combination the front API and the cart enforce.
     */
    public function testTheQuantityOfACartLineIsNotWrittenThroughTheAdminApi(): void
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 100]);
        $combination = $product->getDefaultSaleElements();
        (new QuantityRule(minQuantity: 2.0, step: 6.0))->applyTo($combination);
        $combination->save($this->getPropelConnection());
        $cartItem = $this->factory->cartItem($this->factory->cart(), $product, $combination, ['quantity' => 6]);

        $response = $this->jsonRequest('PATCH', '/api/admin/cart_items/'.$cartItem->getId(), ['quantity' => 7], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertLessThan(500, $response->getStatusCode(), (string) $response->getContent());
        CartItemTableMap::clearInstancePool();
        self::assertSame(6.0, (float) CartItemQuery::create()->findPk($cartItem->getId())?->getQuantity());
    }

    private function combination(QuantityRule $rule): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $combination = $this->factory->productSaleElement($product, ['quantity' => 10]);
        $rule->applyTo($combination);
        $combination->save($this->getPropelConnection());

        return $combination;
    }
}
