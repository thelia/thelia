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

use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\TaxRule;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The front API serves the rule price as the promo price of a sale element, for
 * everyone, and a page of products costs one read of the stored prices.
 */
final class CatalogPriceRulePriceApiTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private Currency $currency;

    private Category $category;

    private TaxRule $taxRule;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->currency = $factory->currency();
        $this->category = $factory->category();
        $this->taxRule = $factory->taxRule();
    }

    public function testAVisitorIsChargedTheRulePrice(): void
    {
        $product = $this->catalogProduct();
        $this->ruleOn([$product], 20.0);

        $payload = $this->readJson('/api/front/products/'.$product->getId());
        $saleElement = $payload['productSaleElements'][0];

        self::assertTrue($saleElement['promo']);
        self::assertTrue($saleElement['displayInitialPrice']);
        self::assertSame(80.0, (float) $saleElement['productPrices'][0]['promoPrice']);
        self::assertSame(100.0, (float) $saleElement['productPrices'][0]['price'], 'The catalog price is untouched.');
    }

    public function testAPageOfProductsReadsTheStoredPricesOnce(): void
    {
        $products = [$this->catalogProduct(), $this->catalogProduct(), $this->catalogProduct()];
        $this->ruleOn($products, 20.0);
        $ids = implode('&id[]=', array_map(static fn (Product $product): int => $product->getId(), $products));

        $payload = [];
        $statements = $this->recordSqlQueries(function () use (&$payload, $ids): void {
            $payload = $this->readJson('/api/front/products?id[]='.$ids);
        });

        self::assertCount(3, $payload['hydra:member']);

        foreach ($payload['hydra:member'] as $member) {
            self::assertTrue($member['productSaleElements'][0]['promo']);
        }

        self::assertSame(1, self::countSqlQueriesSelectingFrom($statements, 'catalog_price_rule_price'), 'one read for the page, not one per member');
    }

    public function testTheAdminReadKeepsTheCatalog(): void
    {
        $product = $this->catalogProduct();
        $this->ruleOn([$product], 20.0);

        $payload = $this->readJson('/api/admin/products/'.$product->getId(), $this->authenticateAsAdmin());

        self::assertFalse($payload['productSaleElements'][0]['promo']);
    }

    public function testAReservedRuleIsChargedToTheNamedCustomerOnly(): void
    {
        $product = $this->catalogProduct();
        $named = $this->newCustomer('password');
        $other = $this->newCustomer('password');
        $this->ruleOn([$product], 30.0, ['audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS], $named);

        $forNamed = $this->readJson('/api/front/products/'.$product->getId(), $this->authenticateAsCustomer($named));
        self::assertTrue($forNamed['productSaleElements'][0]['promo']);
        self::assertSame(70.0, (float) $forNamed['productSaleElements'][0]['productPrices'][0]['promoPrice']);

        $forOther = $this->readJson('/api/front/products/'.$product->getId(), $this->authenticateAsCustomer($other));
        self::assertFalse($forOther['productSaleElements'][0]['promo']);

        $forVisitor = $this->readJson('/api/front/products/'.$product->getId());
        self::assertFalse($forVisitor['productSaleElements'][0]['promo']);
    }

    private function catalogProduct(): Product
    {
        return $this->createFixtureFactory()->product(
            $this->category,
            $this->taxRule,
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => 100.0, 'title' => 'Rule price product'],
        );
    }

    /**
     * @param list<Product> $products
     */
    private function ruleOn(array $products, float $percentage, array $overrides = [], ?Customer $customer = null): CatalogPriceRule
    {
        $factory = $this->createFixtureFactory();
        $rule = $factory->catalogPriceRule($overrides + ['active' => true, 'percentageValue' => $percentage]);

        foreach ($products as $product) {
            $factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        }

        if (null !== $customer) {
            $factory->catalogPriceRuleCustomer($rule, $customer);
        }

        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        return $rule;
    }

    private function newCustomer(?string $password = null): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer($factory->customerTitle(), null === $password ? [] : ['password' => $password]);
    }

    private function readJson(string $uri, ?string $token = null): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $token);
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
