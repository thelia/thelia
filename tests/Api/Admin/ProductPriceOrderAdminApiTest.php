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

use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Currency;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Test\ApiTestCase;

/**
 * On the admin API, "untaxed_price_order" ranks the products on the catalog price of their
 * default combination, as it always did: the `price` column, neither the promotional price nor a
 * rule open to everyone, which the storefront ranks on.
 *
 *   A 85 catalog, B 90 catalog and 80 promotional, C 120 catalog and 30 under a rule
 *
 *   catalog   A 85   B 90   C 120   -> A, B, C
 *   storefront  C 30  B 80  A 85    -> C, B, A
 */
final class ProductPriceOrderAdminApiTest extends ApiTestCase
{
    public function testTheCatalogPriceIsTheOneSorted(): void
    {
        $factory = $this->createFixtureFactory();
        $currency = Currency::getDefaultCurrency();
        $category = $factory->category();
        $taxRule = $factory->taxRule();

        $factory->product($category, $taxRule, $currency, ['ref' => 'ADM-A', 'basePrice' => 85.0, 'baseQuantity' => 10]);
        $b = $factory->product($category, $taxRule, $currency, ['ref' => 'ADM-B', 'basePrice' => 90.0, 'baseQuantity' => 10]);
        $c = $factory->product($category, $taxRule, $currency, ['ref' => 'ADM-C', 'basePrice' => 120.0, 'baseQuantity' => 10]);

        $saleElements = $b->getDefaultSaleElements();
        $saleElements->setPromo(1)->save();
        ProductPriceQuery::create()->filterByProductSaleElementsId($saleElements->getId())->filterByCurrencyId($currency->getId())->findOne()->setPromoPrice('80')->save();

        $rule = $factory->catalogPriceRule(['active' => true, 'percentageValue' => 75.0]);
        $factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, ProductQuery::create()->findOneByRef($c->getRef())->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        $token = $this->authenticateAsAdmin();

        self::assertSame(['ADM-A', 'ADM-B', 'ADM-C'], $this->refsOf('untaxed_price_order=asc', $token));
        self::assertSame(['ADM-C', 'ADM-B', 'ADM-A'], $this->refsOf('untaxed_price_order=desc', $token));
    }

    /**
     * @return list<string>
     */
    private function refsOf(string $query, string $token): array
    {
        $response = $this->jsonRequest('GET', '/api/admin/products?ref[]=ADM-A&ref[]=ADM-B&ref[]=ADM-C&'.$query, token: $token);
        self::assertJsonResponseSuccessful($response);

        $decoded = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return array_column($decoded['hydra:member'], 'ref');
    }
}
