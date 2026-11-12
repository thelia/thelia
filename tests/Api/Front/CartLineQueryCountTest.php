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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Integration\Domain\Pricing\PriceList\PriceListFixtures;

/**
 * The sale element of a cart line is read without its prices. Whether a price list may close it
 * to the caller is asked only while a price list is on: a shop whose group has a discount and
 * no price list pays nothing per line for it, whatever the size of the cart.
 */
final class CartLineQueryCountTest extends ApiTestCase
{
    use PriceListFixtures;
    use RecordsSqlQueries;

    /** The reads that price a sale element: the base prices, then what the groups, the rules, the sales and the lists add. */
    private const PRICING_STATEMENTS = '/pse\.id AS pse_id|FROM `(product_price|customer_group_product_price|catalog_price_rule\w*|sale\w*|price_list\w*)`/';

    private ?FixtureFactory $factory = null;

    public function testAShopWithAGroupDiscountAndNoPriceListReadsTheLinesOfACartWithoutAStatementPerLine(): void
    {
        $group = $this->factory()->customerGroup('discounted', 10.0);

        $few = $this->pricingStatementsOfReadingACartOf(3, $group);
        $many = $this->pricingStatementsOfReadingACartOf(30, $group);

        self::assertSame(\count($few), \count($many), "3 lines:\n".implode("\n", $few)."\n30 lines:\n".implode("\n", $many));
        self::assertSame([], array_values(array_filter($many, static fn (string $statement): bool => str_contains($statement, 'pse.id AS pse_id'))), 'no price is resolved for a line: nothing prices it before the lists either');
    }

    public function testAnExclusivePriceListStillClosesTheLinesItDoesNotHold(): void
    {
        $factory = $this->factory();
        $group = $factory->customerGroup('discounted-with-list', 10.0);
        $list = $this->priceList('EXCL-CART', exclusive: true);
        $this->assignToGroup($list, $group);
        [$token, $cart, $products] = $this->cartOf(2, $group);
        $this->priceListItem($list, $products[0]->getDefaultSaleElements(), '5');
        $this->forgetRequestMemos();

        $payload = $this->readCart($token, $cart);

        $purchasable = [];

        foreach ($payload['hydra:member'] as $line) {
            $purchasable[(int) $line['productSaleElements']['id']] = $line['productSaleElements']['purchasable'];
        }

        self::assertSame(
            [(int) $products[0]->getDefaultSaleElements()->getId() => true, (int) $products[1]->getDefaultSaleElements()->getId() => false],
            $purchasable,
        );
    }

    /**
     * @return list<string>
     */
    private function pricingStatementsOfReadingACartOf(int $lines, CustomerGroup $group): array
    {
        [$token, $cart] = $this->cartOf($lines, $group);
        $this->forgetRequestMemos();
        $this->readCart($token, $cart);
        $this->forgetRequestMemos();

        $statements = $this->recordSqlQueries(function () use ($token, $cart): void {
            $this->readCart($token, $cart);
        });

        return array_values(array_filter($statements, static fn (string $statement): bool => 1 === preg_match(self::PRICING_STATEMENTS, $statement)));
    }

    /**
     * @return array<string, mixed>
     */
    private function readCart(string $token, Cart $cart): array
    {
        $response = $this->jsonRequest('GET', '/api/front/cart_items?cart.id='.$cart->getId().'&itemsPerPage=100', token: $token);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{string, Cart, list<Product>}
     */
    private function cartOf(int $lines, CustomerGroup $group): array
    {
        $factory = $this->factory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password', 'customerGroup' => $group]);
        $cart = $factory->cart($customer);
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $products = [];

        for ($index = 0; $index < $lines; ++$index) {
            $products[] = $product = $factory->product($category, $taxRule, Currency::getDefaultCurrency(), ['basePrice' => 40.0, 'baseQuantity' => 100]);
            $factory->cartItem($cart, $product);
        }

        return [$this->authenticateAsCustomer($customer), $cart, $products];
    }

    private function factory(): FixtureFactory
    {
        return $this->factory ??= $this->createFixtureFactory();
    }
}
