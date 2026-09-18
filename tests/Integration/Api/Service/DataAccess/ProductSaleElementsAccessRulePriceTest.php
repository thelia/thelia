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

namespace Thelia\Tests\Integration\Api\Service\DataAccess;

use Thelia\Api\Service\DataAccess\ProductSaleElementsAccessService;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;

/**
 * The product page reads its sale elements through the access service: the rule
 * price is what it reads as the promo price.
 */
final class ProductSaleElementsAccessRulePriceTest extends IntegrationTestCase
{
    public function testAVisitorReadsTheRulePrice(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['basePrice' => 100.0, 'baseQuantity' => 100]);
        $rule = $factory->catalogPriceRule(['active' => true, 'percentageValue' => 20.0]);
        $factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        $saleElement = $this->firstSaleElementOf($product);

        self::assertTrue($saleElement['isPromo']);
        self::assertEqualsWithDelta(80.0, (float) $saleElement['promoUntaxedPrice'], 0.000001);
        self::assertEqualsWithDelta(100.0, (float) $saleElement['untaxedPrice'], 0.000001);
    }

    private function firstSaleElementOf(Product $product): array
    {
        $saleElements = json_decode(
            (string) $this->getService(ProductSaleElementsAccessService::class)->psesByProduct($product->getId()),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );

        self::assertNotSame([], $saleElements);

        return $saleElements[0];
    }
}
