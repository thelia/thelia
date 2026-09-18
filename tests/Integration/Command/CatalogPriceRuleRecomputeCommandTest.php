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

namespace Thelia\Tests\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceReader;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRulePriceQuery;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The catch-up: what the events could not finish inline is finished here, and the
 * stored segments already over are dropped.
 */
final class CatalogPriceRuleRecomputeCommandTest extends ActionIntegrationTestCase
{
    public function testTheDirtyRulesAreRecomputedAndTheExpiredSegmentsPurged(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency, ['basePrice' => 100.0]);
        $pse = $this->defaultPseFor($product);

        // A rule written behind the repricer's back, as a too-large change leaves it.
        $rule = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 20.0]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $rule->setDirty(true)->save();

        // A segment already over, as an ended rule leaves one behind.
        $expired = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 50.0, 'endDate' => new \DateTime('-1 day')]);
        $this->getPropelConnection()
            ->exec(\sprintf(
                "INSERT INTO catalog_price_rule_price (product_sale_elements_id, currency_id, valid_from, valid_until, price, catalog_price_rule_id, display_initial_price) VALUES (%d, %d, '2000-01-01 00:00:00', '2000-02-01 00:00:00', 50, %d, 1)",
                $pse->getId(),
                $currency->getId(),
                $expired->getId(),
            ));

        $tester = new CommandTester((new Application(self::$kernel))->find('catalog-price-rule:recompute'));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('1 expired price segment(s) purged', $tester->getDisplay());
        self::assertStringContainsString('1 dirty rule(s) recomputed', $tester->getDisplay());
        self::assertFalse((bool) CatalogPriceRuleQuery::create()->findPk($rule->getId())->getDirty());
        self::assertEqualsWithDelta(80.0, $this->getService(PublicPriceReader::class)->currentPrices([$pse->getId()], $currency)[$pse->getId()]->untaxedPrice, 0.000001);
        self::assertSame(0, CatalogPriceRulePriceQuery::create()->filterByCatalogPriceRuleId($expired->getId())->count());
    }

    public function testAFullRunRebuildsEverything(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency, ['basePrice' => 100.0]);
        $pse = $this->defaultPseFor($product);
        $rule = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 20.0]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());

        $tester = new CommandTester((new Application(self::$kernel))->find('catalog-price-rule:recompute'));
        $tester->execute(['--full' => true]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertArrayHasKey($pse->getId(), $this->getService(PublicPriceReader::class)->currentPrices([$pse->getId()], $currency));
    }

    public function testAnUnknownRuleIsRefused(): void
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('catalog-price-rule:recompute'));
        $tester->execute(['--rule' => 999_999_999]);

        self::assertSame(1, $tester->getStatusCode());
    }

    private function defaultPseFor(Product $product): ProductSaleElements
    {
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();
        self::assertNotNull($pse);

        return $pse;
    }
}
