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

namespace Thelia\Tests\Integration\Domain\Promotion;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Api\Service\DataAccess\AttributeAccessService;
use Thelia\Model\Lang;
use Thelia\Model\ProductQuery;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The cart lines a theme prints are read for a number of queries that does not grow with
 * the cart: a browser starts with nothing pooled, so the count is taken with the pools empty.
 */
final class CartItemsAttributeQueryCountTest extends ActionIntegrationPromotionTestCase
{
    use ForgetsPooledModels;
    use RecordsSqlQueries;

    protected function setUp(): void
    {
        parent::setUp();

        AttributeAccessService::clearCache();
    }

    public function testTheLinesAreReadByANumberOfQueriesThatDoesNotGrowWithTheCart(): void
    {
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => 10.0, 'title' => 'Line 1']));

        // What the process keeps between two reads (rates of a tax rule, settings) is read once.
        $this->queriesReadingTheLines();
        $oneLine = $this->queriesReadingTheLines();

        foreach ([20.0, 30.0, 40.0] as $index => $price) {
            $this->addItem($cart, $this->product(['basePrice' => $price, 'title' => 'Line '.($index + 2)]));
        }

        $fourLines = $this->queriesReadingTheLines();

        self::assertSame($oneLine, $fourLines, 'Reading the lines costs queries per line.');
    }

    /**
     * The request a theme prints a cart in has its products in the pool already (a listing, a
     * product page read them): they are the ones every other read shares, and reading them again
     * costs a query the pool exists to save. Pooling is on here, as in production, and the products
     * are pooled with their title before the lines are read.
     */
    public function testProductsAlreadyInThePoolAreNotReadAgain(): void
    {
        $cart = $this->newEmptyCart();
        $products = [
            $this->product(['basePrice' => 10.0, 'title' => 'Pooled 1']),
            $this->product(['basePrice' => 20.0, 'title' => 'Pooled 2']),
        ];

        foreach ($products as $product) {
            $this->addItem($cart, $product);
        }

        $productIds = array_map(static fn ($product): int => (int) $product->getId(), $products);
        $wasEnabled = Propel::isInstancePoolingEnabled();
        Propel::enableInstancePooling();

        try {
            self::forgetPooledModels();

            foreach (ProductQuery::create()->joinWithTaxRule(Criteria::LEFT_JOIN)->filterById($productIds, Criteria::IN)->find() as $pooled) {
                // The title in the locale the lines read it in, held by the pooled product.
                self::assertNotSame('', (string) $pooled->setLocale(Lang::getDefaultLanguage()->getLocale())->getTitle());
            }

            AttributeAccessService::clearCache();
            $attributeAccess = $this->getService(AttributeAccessService::class);
            $lines = [];
            $statements = $this->recordSqlQueries(static function () use ($attributeAccess, &$lines): void {
                $lines = $attributeAccess->attributeCart('cart_items');
            });

            self::assertCount(2, $lines);
            self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'product'), 'a pooled product is read again: '.implode(' | ', $statements));
            self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'product_i18n'), 'a pooled title is read again: '.implode(' | ', $statements));
        } finally {
            self::forgetPooledModels();

            if (!$wasEnabled) {
                Propel::disableInstancePooling();
            }
        }
    }

    private function queriesReadingTheLines(): int
    {
        AttributeAccessService::clearCache();
        $attributeAccess = $this->getService(AttributeAccessService::class);

        $lines = [];
        $statements = $this->recordSqlQueriesWithoutPooledModels(static function () use ($attributeAccess, &$lines): void {
            $lines = $attributeAccess->attributeCart('cart_items');
        });

        self::assertNotSame([], $lines);

        return \count($statements);
    }
}
