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

namespace Thelia\Tests\Http\Flexy;

use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * Nothing was written in the theme for the rules: the product page reads the rule
 * price the way it reads a promo price, and shows the discount it amounts to.
 */
final class CatalogPriceRuleShowcaseTest extends WebIntegrationTestCase
{
    private const PRODUCT_URL = 'flexy-catalog-price-rule-test.html';

    private const PRODUCT_TEMPLATE = 'product.html.twig';

    protected function setUp(): void
    {
        parent::setUp();

        $frontTemplate = $this->getService(TemplateHelperInterface::class)->getActiveFrontTemplate();
        $productPage = $frontTemplate->getAbsolutePath().\DIRECTORY_SEPARATOR.self::PRODUCT_TEMPLATE;

        if (!file_exists($productPage) || !str_contains((string) file_get_contents($productPage), 'ProductDetails')) {
            self::markTestSkipped('The installed front-office theme has no product details page.');
        }
    }

    public function testTheProductPageShowsTheDiscountTheRuleAmountsTo(): void
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $product = $this->product($factory, 'Rule showcase product');
        $product->setRewrittenUrl('en_US', self::PRODUCT_URL);

        $rule = $factory->catalogPriceRule(['active' => true, 'percentageValue' => 20.0]);
        $factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        $this->assertPageRenders('/'.self::PRODUCT_URL);

        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('Rule showcase product', $content);
        self::assertStringContainsString('-20%', $content, 'The page tags the product with the discount the rule amounts to.');
    }

    private function product(FixtureFactory $factory, string $title): Product
    {
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['basePrice' => 100.0, 'baseQuantity' => 100]);
        $product->setLocale('en_US')->setTitle($title)->save($this->getPropelConnection());

        return $product;
    }
}
