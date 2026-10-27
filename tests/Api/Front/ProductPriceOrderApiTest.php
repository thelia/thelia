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

use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\TaxRule;
use Thelia\Test\ApiTestCase;

/**
 * "untaxed_price_order" sorts the collection on the public catalog price of the default
 * combination, in the currency browsed. What a customer alone is owed (the price of their
 * group, a rule, a discount) is resolved per customer in PHP and is not in the database:
 * it stays out of the sort, whoever asks.
 */
final class ProductPriceOrderApiTest extends ApiTestCase
{
    private Currency $currency;

    private Category $category;

    private TaxRule $taxRule;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->currency = Currency::getDefaultCurrency();
        $this->category = $factory->category();
        $this->taxRule = $factory->taxRule();
    }

    public function testTheSortReadsTheDefaultCombinationOnly(): void
    {
        $dear = $this->product('DEAR', 50.0);
        $this->product('CHEAP', 20.0);
        // A cheaper combination that is not the default one does not move the product.
        $factory = $this->createFixtureFactory();
        $factory->productPrice($factory->productSaleElement($dear), $this->currency, ['price' => '5.000000', 'promoPrice' => '5.000000']);

        self::assertSame(['CHEAP', 'DEAR'], $this->refsOf('untaxed_price_order=asc'));
        self::assertSame(['DEAR', 'CHEAP'], $this->refsOf('untaxed_price_order=desc'));
        self::assertSame(2, $this->totalOf('untaxed_price_order=asc'), 'One row per product.');
    }

    public function testTheSortReadsThePriceOfTheCurrencyBrowsed(): void
    {
        $quid = $this->createFixtureFactory()->currency(['code' => 'QQQ', 'symbol' => 'Q', 'rate' => 2.0, 'byDefault' => 0]);
        $typed = $this->product('TYPED', 50.0);
        $this->product('CONVERTED', 20.0);
        // 50 in the default currency, but typed 10 in the other one: cheaper there.
        $this->createFixtureFactory()->productPrice($typed->getDefaultSaleElements(), $quid, ['price' => '10.000000', 'promoPrice' => '10.000000']);

        self::assertSame(['CONVERTED', 'TYPED'], $this->refsOf('untaxed_price_order=asc'));
        // CONVERTED is 20 in the default currency, converted at the rate of the other one.
        self::assertSame(['TYPED', 'CONVERTED'], $this->refsOf('untaxed_price_order=asc&currency=QQQ'));
    }

    public function testACustomerIsSortedOnThePublicPricesToo(): void
    {
        $factory = $this->createFixtureFactory();
        $dear = $this->product('DEAR', 50.0);
        $this->product('CHEAP', 20.0);
        $member = $factory->customer($factory->customerTitle(), ['customerGroup' => $factory->customerGroup(discount: 0.0), 'password' => 'password']);
        $factory->customerGroupProductPrice($member->getCustomerGroup(), $dear->getDefaultSaleElements(), $this->currency, 5.0);

        $token = $this->authenticateAsCustomer($member);

        // DEAR costs this member 5, and the sort says it is the dearer: it ranks on the catalog.
        self::assertSame(['CHEAP', 'DEAR'], $this->refsOf('untaxed_price_order=asc', $token));
    }

    private function product(string $ref, float $price): Product
    {
        return $this->createFixtureFactory()->product($this->category, $this->taxRule, $this->currency, ['ref' => $ref, 'basePrice' => $price, 'baseQuantity' => 10]);
    }

    /**
     * @return list<string>
     */
    private function refsOf(string $query, ?string $token = null): array
    {
        return array_column($this->decode($query, $token)['hydra:member'], 'ref');
    }

    private function totalOf(string $query): int
    {
        return (int) $this->decode($query)['hydra:totalItems'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $query, ?string $token = null): array
    {
        $response = $this->jsonRequest('GET', '/api/front/products?'.$query, token: $token);
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
