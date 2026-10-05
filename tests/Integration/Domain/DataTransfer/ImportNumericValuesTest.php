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

namespace Thelia\Tests\Integration\Domain\DataTransfer;

use Thelia\Domain\DataTransfer\Import\Type\ProductPricesImport;
use Thelia\Domain\DataTransfer\Import\Type\ProductStockImport;
use Thelia\Model\Currency;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * A spreadsheet hands every cell over as text, a JSON file hands numbers: the stock
 * and price imports read either, and refuse a row whose cell is not a number with the
 * reason instead of failing the whole import.
 */
final class ImportNumericValuesTest extends IntegrationTestCase
{
    private ProductSaleElements $combination;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $this->combination = $factory->productSaleElement($product, ['quantity' => 5]);
    }

    public function testAStockGivenAsTextIsWritten(): void
    {
        self::assertNull((new ProductStockImport())->importData(['id' => $this->combination->getId(), 'stock' => '12']));

        self::assertSame(12.0, (float) ProductSaleElementsQuery::create()->findPk($this->combination->getId())?->getQuantity());
    }

    public function testAStockThatIsNotANumberRefusesTheRowAndSaysWhy(): void
    {
        $error = (new ProductStockImport())->importData(['id' => $this->combination->getId(), 'stock' => 'twelve']);

        self::assertNotNull($error);
        self::assertStringContainsString('twelve', $error);
        self::assertSame(5.0, (float) ProductSaleElementsQuery::create()->findPk($this->combination->getId())?->getQuantity());
    }

    public function testAPriceGivenAsANumberIsWritten(): void
    {
        self::assertNull((new ProductPricesImport())->importData(['id' => $this->combination->getId(), 'price' => 19.5, 'promo_price' => 15]));

        $price = ProductPriceQuery::create()
            ->filterByProductSaleElementsId($this->combination->getId())
            ->findOneByCurrencyId(Currency::getDefaultCurrency()->getId());

        self::assertNotNull($price);
        self::assertSame(19.5, (float) $price->getPrice());
        self::assertSame(15.0, (float) $price->getPromoPrice());
    }

    public function testAPriceThatIsNotANumberRefusesTheRow(): void
    {
        $error = (new ProductPricesImport())->importData(['id' => $this->combination->getId(), 'price' => 'free']);

        self::assertNotNull($error);
        self::assertStringContainsString('price', $error);
    }
}
