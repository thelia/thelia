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

namespace Thelia\Tests\Integration\Domain\Pricing;

use Thelia\Domain\Pricing\EffectivePrice;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\EffectivePriceResolver;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * One price per product for the product loop: the lowest effective price among its
 * sale elements.
 */
final class EffectivePriceCatalogTest extends ActionIntegrationTestCase
{
    use RecordsSqlQueries;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = $this->factory->currency();
    }

    public function testEachProductGetsTheLowestEffectivePriceAmongItsSaleElements(): void
    {
        // The cheapest sale element sits between two dearer ones, whichever order they are read in.
        $withThreeSaleElements = $this->product();
        $dearer = $this->defaultPseFor($withThreeSaleElements);
        $cheapest = $this->factory->productSaleElement($withThreeSaleElements);
        $dearest = $this->factory->productSaleElement($withThreeSaleElements);
        $withOneSaleElement = $this->product();
        $only = $this->defaultPseFor($withOneSaleElement);

        $catalog = $this->catalogPricing(
            [$dearer->getId() => 50.0, $cheapest->getId() => 30.0, $dearest->getId() => 60.0, $only->getId() => 70.0],
            publicRule: true,
        );

        $lowest = $catalog->lowestForProducts([$withThreeSaleElements->getId(), $withOneSaleElement->getId()], $this->currency, null);

        self::assertEqualsCanonicalizing([$withThreeSaleElements->getId(), $withOneSaleElement->getId()], array_keys($lowest));
        self::assertSame($cheapest->getId(), $lowest[$withThreeSaleElements->getId()]->productSaleElementsId);
        self::assertSame(30.0, $lowest[$withThreeSaleElements->getId()]->untaxedPromoPrice);
        self::assertSame($only->getId(), $lowest[$withOneSaleElement->getId()]->productSaleElementsId);
    }

    public function testNoSaleElementIsReadWhileNothingPricesForTheVisitor(): void
    {
        $product = $this->product();
        $catalog = $this->catalogPricing([$this->defaultPseFor($product)->getId() => 50.0], publicRule: false);

        $lowest = [];
        $statements = $this->recordSqlQueries(function () use ($catalog, $product, &$lowest): void {
            $lowest = $catalog->lowestForProducts([$product->getId()], $this->currency, null);
        });

        self::assertSame([], $lowest);
        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'product_sale_elements'));
    }

    /**
     * @param array<int, float> $untaxedPricesBySaleElement
     */
    private function catalogPricing(array $untaxedPricesBySaleElement, bool $publicRule): EffectivePriceCatalog
    {
        $resolver = $this->createStub(EffectivePriceResolver::class);
        $resolver->method('resolve')->willReturnCallback(
            static function (array $productSaleElementsIds) use ($untaxedPricesBySaleElement): array {
                $prices = [];

                foreach ($productSaleElementsIds as $pseId) {
                    if (isset($untaxedPricesBySaleElement[$pseId])) {
                        $prices[$pseId] = new EffectivePrice($pseId, $untaxedPricesBySaleElement[$pseId], false, EffectivePrice::SOURCE_RULE, 1);
                    }
                }

                return $prices;
            },
        );

        $activityChecker = $this->createStub(PricingActivityChecker::class);
        $activityChecker->method('hasActivePublicRule')->willReturn($publicRule);
        $activityChecker->method('hasActiveNamedRule')->willReturn(false);

        $saleAudienceChecker = $this->createStub(SaleAudienceChecker::class);
        $saleAudienceChecker->method('hasActiveReservedSale')->willReturn(false);

        return new EffectivePriceCatalog($resolver, $activityChecker, $saleAudienceChecker);
    }

    private function product(): Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency, ['basePrice' => 100.0]);
    }

    private function defaultPseFor(Product $product): ProductSaleElements
    {
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();
        self::assertNotNull($pse);

        return $pse;
    }
}
