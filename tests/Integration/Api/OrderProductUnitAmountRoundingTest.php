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

namespace Thelia\Tests\Integration\Api;

use Thelia\Api\Resource\OrderProduct;
use Thelia\Api\Resource\OrderProductTax;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderProductTax as OrderProductTaxModel;
use Thelia\Test\IntegrationTestCase;

/**
 * The API exposes the unit price and the unit taxes of an order line, and a
 * client multiplies them by the quantity to show the line. So they have to carry
 * the precision the order total was actually built from.
 *
 * Under sum of roundings the total multiplies unit amounts already rounded to
 * the cent, and the cent is what the customer was charged on. Under rounding of
 * sums the total keeps the stored precision: a unit price of 0.005678 € the gram
 * cut to 0.01 € would multiply up to 3.00 € on a 300 g line the order charges
 * 1.80 € for.
 */
final class OrderProductUnitAmountRoundingTest extends IntegrationTestCase
{
    /** One gram of a product priced 5.678 € the kilogram. */
    private const PRICE_PER_GRAM = 0.005678;

    private const TAX_PER_GRAM = 0.000312;

    private const ORDER_ID = 4242;

    protected function tearDown(): void
    {
        // ConfigQuery caches in a static array the transaction rollback cannot
        // reach, so a mode written here would answer the next test.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheDefaultModeExposesUnitAmountsRoundedToTheCent(): void
    {
        $orderProduct = $this->orderProductResource();

        self::assertSame(0.01, $orderProduct->getPrice());
        self::assertSame(0.01, $orderProduct->getPromoPrice());
        self::assertSame(0.01, $orderProduct->getUnitTaxedPrice());
        self::assertSame(0.0, $this->orderProductTaxResource()->getAmount());
    }

    public function testRoundingOfSumsExposesUnitAmountsAtTheStoredPrecision(): void
    {
        $this->optIn();

        $orderProduct = $this->orderProductResource();

        self::assertSame(self::PRICE_PER_GRAM, $orderProduct->getPrice());
        self::assertSame(self::PRICE_PER_GRAM, $orderProduct->getPromoPrice());
        self::assertSame(self::PRICE_PER_GRAM, $orderProduct->getUnitTaxedPrice());
        self::assertSame(self::TAX_PER_GRAM, $this->orderProductTaxResource()->getAmount());
    }

    /**
     * An order frozen by the pivot was invoiced with sum of roundings, so it
     * keeps showing the unit amounts that total up to the amount charged, even
     * once the shop has opted in.
     */
    public function testAnOrderFrozenByThePivotKeepsCentUnitAmounts(): void
    {
        ConfigQuery::write('last_sum_of_roundings_order_id', (string) self::ORDER_ID);
        $this->optIn();

        self::assertSame(0.01, $this->orderProductResource()->getPrice());
        self::assertSame(0.0, $this->orderProductTaxResource()->getAmount());
    }

    /**
     * A line that was never in promotion carries no promo amount, and rounding
     * one would answer 0.00 for "no promotion" -- besides deprecating a null.
     */
    public function testAnAbsentPromoAmountStaysAbsent(): void
    {
        self::assertNull($this->orderProductTaxResource()->getPromoAmount());

        $this->optIn();

        self::assertNull($this->orderProductTaxResource()->getPromoAmount());
    }

    private function optIn(): void
    {
        ConfigQuery::write('order_rounding_mode', (string) ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS);
    }

    private function orderProductResource(): OrderProduct
    {
        $resource = new OrderProduct();
        $resource->setPropelModel($this->orderProductModel());
        $resource->setPrice(self::PRICE_PER_GRAM);
        $resource->setPromoPrice(self::PRICE_PER_GRAM);
        $resource->unitTaxedPrice = self::PRICE_PER_GRAM;

        return $resource;
    }

    private function orderProductTaxResource(): OrderProductTax
    {
        $propelModel = new OrderProductTaxModel();
        $propelModel->setOrderProduct($this->orderProductModel());

        $resource = new OrderProductTax();
        $resource->setPropelModel($propelModel);
        $resource->setAmount(self::TAX_PER_GRAM);

        return $resource;
    }

    private function orderProductModel(): OrderProductModel
    {
        $propelModel = new OrderProductModel();
        $propelModel->setOrderId(self::ORDER_ID);

        return $propelModel;
    }
}
