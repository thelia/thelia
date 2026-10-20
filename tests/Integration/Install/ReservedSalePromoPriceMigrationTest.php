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

namespace Thelia\Tests\Integration\Install;

use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\Sale;
use Thelia\Test\IntegrationTestCase;

/**
 * The statement 3.3.0.sql runs for the shops where a public sale was switched to a
 * reserved audience before this release: the discount it left in `promo_price` is
 * given back the catalog price, unless a public promotion still flags the product.
 */
final class ReservedSalePromoPriceMigrationTest extends IntegrationTestCase
{
    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = $this->createFixtureFactory()->currency();
    }

    public function testTheDiscountLeftByASaleTurnedReservedIsGivenBackTheCatalogPrice(): void
    {
        $leaking = $this->productOnSale(promo: false, promoPrice: '90.000000');
        $publicPromotion = $this->productOnSale(promo: true, promoPrice: '80.000000');
        $outsideAnySale = $this->product(promo: false, promoPrice: '70.000000');

        $this->runMigration();

        self::assertSame(100.0, $this->promoPriceOf($leaking), 'the reserved discount stays readable');
        self::assertSame(80.0, $this->promoPriceOf($publicPromotion), 'a running public promotion is left alone');
        self::assertSame(70.0, $this->promoPriceOf($outsideAnySale), 'a product no reserved sale covers is left alone');
    }

    public function testTheMigrationReplays(): void
    {
        $leaking = $this->productOnSale(promo: false, promoPrice: '90.000000');

        $this->runMigration();
        $this->runMigration();

        self::assertSame(100.0, $this->promoPriceOf($leaking));
    }

    private function productOnSale(bool $promo, string $promoPrice): Product
    {
        $factory = $this->createFixtureFactory();
        $product = $this->product($promo, $promoPrice);

        $sale = $factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'priceOffsetType' => Sale::OFFSET_TYPE_PERCENTAGE,
        ]);
        $factory->saleProduct($sale, $product);

        return $product;
    }

    /**
     * A product priced 100, in the state the update finds it: its promo flag and the
     * promo price a sale or the merchant wrote.
     */
    private function product(bool $promo, string $promoPrice): Product
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $this->currency, ['basePrice' => 100.0]);

        $saleElement = $product->getDefaultSaleElements();
        $saleElement->setPromo($promo ? 1 : 0)->save($this->getPropelConnection());

        foreach ($saleElement->getProductPrices() as $price) {
            $price->setPromoPrice($promoPrice)->save($this->getPropelConnection());
        }

        return $product;
    }

    private function promoPriceOf(Product $product): float
    {
        return (float) ProductPriceQuery::create()
            ->filterByProductSaleElementsId($product->getDefaultSaleElements()->getId())
            ->filterByCurrencyId($this->currency->getId())
            ->findOne($this->getPropelConnection())
            ->getPromoPrice();
    }

    private function runMigration(): void
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.3.0.sql');

        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (str_starts_with($sql, 'UPDATE `product_price`')) {
                $statements[] = $sql;
            }
        }

        self::assertCount(1, $statements, 'The script holds the statement of the reserved sale discount.');

        foreach ($statements as $statement) {
            $this->getPropelConnection()->exec($statement);
        }
    }
}
