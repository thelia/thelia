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

use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Catalog\Stock\VisitorCart;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * What the front API tells of the stock in a listing of products: the number a visitor may buy and
 * whether the next unit is a preorder, for every combination of the page, with a number of reads
 * that does not grow with the page; and what the customer of a token holds is not held against them.
 */
final class ProductListAvailabilityApiTest extends ApiTestCase
{
    use RecordsSqlQueries;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'checkout');
        ConfigQuery::resetCache();
        // The transaction this test wraps itself in is not one of a caller: a hold runs in it.
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        $this->getService(TestCallerTransaction::class)->reset();
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAListingServesWhetherTheNextUnitIsAPreorderAndTheModeOfEveryCombination(): void
    {
        $factory = $this->createFixtureFactory();
        $open = $this->product(0, OutOfStockPolicy::Unlimited);
        $inStock = $this->product(5, OutOfStockPolicy::Unlimited);
        $refusing = $this->product(0, OutOfStockPolicy::Refuse);
        $other = $factory->cart(null);
        $factory->cartItem($other, $inStock, null, ['quantity' => 5.0]);
        $this->getService(CheckoutFacade::class)->holdStock($other);

        $members = $this->membersById('/api/front/products?itemsPerPage=100');

        $combination = static fn (Product $product): array => $members[(int) $product->getId()]['productSaleElements'][0];
        self::assertTrue($combination($open)['isPreorder'], 'Nothing in stock, sells without limit: a preorder.');
        self::assertSame(1, $combination($open)['outOfStockPolicy']);
        self::assertTrue($combination($inStock)['isPreorder'], 'Everything in stock is held by another cart: the next unit is a preorder.');
        self::assertFalse($combination($refusing)['isPreorder']);
        self::assertSame(0, $combination($refusing)['outOfStockPolicy'], 'The mode is served in a listing too.');
        self::assertSame(0.0, (float) $combination($refusing)['availableQuantity']);
    }

    public function testTheReservationsOfAListingAreReadOnceWhateverItsSize(): void
    {
        foreach (range(1, 24) as $_) {
            $product = $this->product(5);
            $this->createFixtureFactory()->productSaleElement($product);
            $this->createFixtureFactory()->productSaleElement($product);
        }

        $reads = fn (string $uri): int => \count(array_filter(
            $this->recordSqlQueries(function () use ($uri): void {
                self::assertJsonResponseSuccessful($this->jsonRequest('GET', $uri));
            }),
            static fn (string $statement): bool => str_contains($statement, 'SUM(`cart_item`.`quantity`)'),
        ));

        self::assertSame(1, $reads('/api/front/products?itemsPerPage=2'));
        self::assertSame(1, $reads('/api/front/products?itemsPerPage=24'), 'Twenty-four products of three combinations: as many reads as two.');
        self::assertSame(1, $reads('/api/front/product_sale_elements?itemsPerPage=24'));
    }

    public function testTheCartOfTheCustomerOfATokenIsNotHeldAgainstThem(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $this->product(1);
        $holder = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $stranger = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($holder);
        $factory->cartItem($cart, $product, null, ['quantity' => 1.0]);
        $this->getService(CheckoutFacade::class)->holdStock($cart);

        self::assertSame(1.0, $this->availableTo($product, $this->authenticateAsCustomer($holder)), 'What the customer holds at the checkout is still theirs.');
        self::assertSame(0.0, $this->availableTo($product, $this->authenticateAsCustomer($stranger)), 'Another customer sees it gone.');
        self::assertSame(0.0, $this->availableTo($product, null), 'So does a visitor without token.');
    }

    private function availableTo(Product $product, ?string $token): float
    {
        $this->getService(VisitorCart::class)->reset();
        $response = $this->jsonRequest('GET', '/api/front/products/'.$product->getId(), token: $token);
        self::assertJsonResponseSuccessful($response);

        return (float) (self::decodeJson($response)['productSaleElements'][0]['availableQuantity'] ?? 0.0);
    }

    private function product(int $quantity, OutOfStockPolicy $policy = OutOfStockPolicy::Refuse): Product
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => $quantity]);
        $product->getDefaultSaleElements()->setQuantity($quantity)->setOutOfStockPolicy($policy->value)->save($this->getPropelConnection());

        return $product;
    }

    /**
     * @return array<int, array<string, mixed>> the members of a listing by id
     */
    private function membersById(string $uri): array
    {
        $response = $this->jsonRequest('GET', $uri);
        self::assertJsonResponseSuccessful($response);
        $members = [];

        foreach (self::decodeJson($response)['hydra:member'] as $member) {
            $members[(int) $member['id']] = $member;
        }

        return $members;
    }
}
