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
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductPrice;
use Thelia\Model\TaxRule;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The front API charges the caller what the cart charges: the price of the group of the
 * customer the token belongs to, then the discount of the group and the discount of the
 * customer, multiplied. A visitor and the back office read the public prices.
 *
 * Catalog price 100.00 untaxed.
 */
final class ProductCustomerPriceApiTest extends ApiTestCase
{
    use RecordsSqlQueries;

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

    public function testAVisitorReadsThePublicPrice(): void
    {
        $product = $this->catalogProduct();

        $saleElement = $this->readJson('/api/front/products/'.$product->getId())['productSaleElements'][0];

        self::assertFalse($saleElement['promo']);
        self::assertSame(100.0, (float) $saleElement['productPrices'][0]['price']);
        self::assertSame(100.0, (float) $saleElement['productPrices'][0]['promoPrice']);
    }

    public function testTheBearerOfATokenIsChargedTheDiscountsOfTheirGroupThenTheirOwn(): void
    {
        $product = $this->catalogProduct();
        $member = $this->member(15.0, 10.0);

        $saleElement = $this->readJson('/api/front/products/'.$product->getId(), $this->authenticateAsCustomer($member))['productSaleElements'][0];

        // 100 x (1 - 15 %) x (1 - 10 %), what the cart charges.
        self::assertSame(76.5, round((float) $saleElement['productPrices'][0]['price'], 6));
        self::assertSame(76.5, round((float) $saleElement['productPrices'][0]['promoPrice'], 6));
        self::assertFalse($saleElement['promo']);
    }

    public function testTheDiscountOfACustomerAloneAppliesToo(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->member(0.0, 10.0);

        $saleElement = $this->readJson('/api/front/products/'.$product->getId(), $this->authenticateAsCustomer($customer))['productSaleElements'][0];

        self::assertSame(90.0, round((float) $saleElement['productPrices'][0]['price'], 6));
    }

    public function testThePriceOfTheGroupIsServedWithItsPromotion(): void
    {
        $product = $this->catalogProduct();
        $member = $this->member(15.0, 10.0);
        $this->createFixtureFactory()->customerGroupProductPrice($member->getCustomerGroup(), $product->getDefaultSaleElements(), $this->currency, 42.0, 30.0);

        $saleElement = $this->readJson('/api/front/products/'.$product->getId(), $this->authenticateAsCustomer($member))['productSaleElements'][0];

        self::assertTrue($saleElement['promo']);
        self::assertTrue($saleElement['displayInitialPrice']);
        self::assertSame(37.8, round((float) $saleElement['productPrices'][0]['price'], 6));
        self::assertSame(27.0, round((float) $saleElement['productPrices'][0]['promoPrice'], 6));

        $forVisitor = $this->readJson('/api/front/products/'.$product->getId())['productSaleElements'][0];
        self::assertFalse($forVisitor['promo']);
        self::assertSame(100.0, (float) $forVisitor['productPrices'][0]['price'], 'The price of a group is not a price of the catalog.');
    }

    public function testAListIsPricedForTheBearerOfTheTokenAndNobodyElse(): void
    {
        $products = [$this->catalogProduct(), $this->catalogProduct()];
        $member = $this->member(20.0);
        $uri = '/api/front/products?id[]='.implode('&id[]=', array_map(static fn (Product $product): int => $product->getId(), $products));

        foreach ($this->readJson($uri, $this->authenticateAsCustomer($member))['hydra:member'] as $listed) {
            self::assertSame(80.0, round((float) $listed['productSaleElements'][0]['productPrices'][0]['price'], 6));
        }

        foreach ($this->readJson($uri)['hydra:member'] as $listed) {
            self::assertSame(100.0, (float) $listed['productSaleElements'][0]['productPrices'][0]['price']);
        }
    }

    /**
     * Two customers of two groups, then a visitor, then the first customer again, in a row:
     * what the one was served is never served to the other, listed or read on its own.
     */
    public function testTwoCustomersOfTwoGroupsAndAVisitorAreServedTheirOwnPricesInARow(): void
    {
        $product = $this->catalogProduct();
        $first = $this->member(20.0);
        $second = $this->member(50.0, 10.0);
        $firstToken = $this->authenticateAsCustomer($first);
        $secondToken = $this->authenticateAsCustomer($second);
        $item = '/api/front/products/'.$product->getId();
        $list = '/api/front/products?id[]='.$product->getId();

        $priceOf = fn (string $uri, ?string $token): float => round((float) (str_contains($uri, '?')
            ? $this->readJson($uri, $token)['hydra:member'][0]
            : $this->readJson($uri, $token))['productSaleElements'][0]['productPrices'][0]['price'], 6);

        self::assertSame(80.0, $priceOf($item, $firstToken));
        self::assertSame(45.0, $priceOf($item, $secondToken));
        self::assertSame(100.0, $priceOf($item, null));
        self::assertSame(80.0, $priceOf($list, $firstToken));
        self::assertSame(45.0, $priceOf($list, $secondToken));
        self::assertSame(100.0, $priceOf($list, null));
        self::assertSame(80.0, $priceOf($item, $firstToken));
    }

    public function testTheAdminReadKeepsTheCatalog(): void
    {
        $product = $this->catalogProduct();
        $this->member(15.0, 10.0);

        $payload = $this->readJson('/api/admin/products/'.$product->getId(), $this->authenticateAsAdmin());

        self::assertSame(100.0, (float) $payload['productSaleElements'][0]['productPrices'][0]['price']);
    }

    public function testTheCurrencyBrowsedIsTheOneTheDiscountsApplyIn(): void
    {
        $product = $this->catalogProduct();
        $dollar = $this->createFixtureFactory()->currency(['code' => 'QQQ', 'symbol' => 'Q', 'rate' => 2.0, 'byDefault' => 0]);
        (new ProductPrice())
            ->setProductSaleElementsId($product->getDefaultSaleElements()->getId())
            ->setCurrencyId($dollar->getId())
            ->setPrice('0.000000')
            ->setPromoPrice('0.000000')
            ->setFromDefaultCurrency(true)
            ->save($this->getPropelConnection());
        $member = $this->member(20.0);

        $price = $this->readJson('/api/front/products/'.$product->getId().'?currency=QQQ', $this->authenticateAsCustomer($member))['productSaleElements'][0]['productPrices'][0];

        // 100 in the default currency, converted at the rate of the dollar, less 20 %.
        $converted = 100.0 * 2.0 / (float) Currency::getDefaultCurrency()->getRate();
        self::assertEqualsWithDelta($converted * 0.8, (float) $price['price'], 0.000001);
    }

    /**
     * A page of fifty is priced by the same statements as a page of three: the prices of the
     * catalog, those of the group and the effective prices are each read once for the page.
     */
    public function testAPageOfProductsPricedForACustomerCostsTheSameStatementsWhateverItsSize(): void
    {
        $member = $this->member(20.0);
        $token = $this->authenticateAsCustomer($member);
        $products = array_map(fn (): Product => $this->catalogProduct(), range(1, 50));

        foreach (\array_slice($products, 0, 25) as $product) {
            $this->createFixtureFactory()->customerGroupProductPrice($member->getCustomerGroup(), $product->getDefaultSaleElements(), $this->currency, 42.0);
        }

        $countFor = function (int $size) use ($products, $token): array {
            $ids = implode('&id[]=', array_map(static fn (Product $product): int => $product->getId(), \array_slice($products, 0, $size)));
            $statements = $this->recordSqlQueries(function () use ($ids, $token, $size): void {
                self::assertCount($size, $this->readJson('/api/front/products?itemsPerPage=50&id[]='.$ids, $token)['hydra:member']);
            });

            return [
                'product_price' => self::countSqlQueriesSelectingFrom($statements, 'product_price'),
                'customer_group_product_price' => self::countSqlQueriesSelectingFrom($statements, 'customer_group_product_price'),
                'customer_group' => self::countSqlQueriesSelectingFrom($statements, 'customer_group'),
            ];
        };

        $small = $countFor(3);
        $large = $countFor(50);

        self::assertSame($small, $large, 'Reads of the prices must not grow with the page.');
        self::assertSame(2, $large['customer_group_product_price'], 'One existence check of the activity checker, one read of the prices of the group for the page.');
    }

    private function catalogProduct(float $basePrice = 100.0): Product
    {
        return $this->createFixtureFactory()->product(
            $this->category,
            $this->taxRule,
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => $basePrice, 'title' => 'Customer price product'],
        );
    }

    private function member(float $groupDiscount, float $customerDiscount = 0.0): Customer
    {
        $factory = $this->createFixtureFactory();
        $member = $factory->customer($factory->customerTitle(), ['customerGroup' => $factory->customerGroup(discount: $groupDiscount), 'password' => 'password']);
        $member->setDiscount((string) $customerDiscount)->save($this->getPropelConnection());

        return $member;
    }

    private function readJson(string $uri, ?string $token = null): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $token);
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
