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
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Sale;
use Thelia\Model\TaxRule;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A reserved operation set to hide its products keeps them out of the catalog
 * of everybody it is not open to.
 *
 * The rule has to hold on the collection and on the direct item read alike: a
 * product left reachable by its id is not hidden, it is only harder to find.
 */
final class ReservedSaleVisibilityApiTest extends ApiTestCase
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

    public function testAVisitorDoesNotSeeTheProductsOfAHiddenReservedOperation(): void
    {
        $product = $this->catalogProduct();
        $this->hiddenReservedSaleOn($product, $this->newCustomer());

        $payload = $this->readJson('/api/front/products');

        self::assertNull(
            $this->memberOrNull($payload, $product->getId()),
            'A hidden reserved operation keeps its products out of the anonymous collection.',
        );
    }

    public function testAVisitorCannotReachThemByTheirIdEither(): void
    {
        $product = $this->catalogProduct();
        $this->hiddenReservedSaleOn($product, $this->newCustomer());

        $response = $this->jsonRequest('GET', '/api/front/products/'.$product->getId());

        self::assertSame(404, $response->getStatusCode());
    }

    public function testTheSaleElementsOfAHiddenProductAreOutOfReachToo(): void
    {
        $product = $this->catalogProduct();
        $this->hiddenReservedSaleOn($product, $this->newCustomer());
        $pse = $this->defaultPseFor($product);

        $collection = $this->readJson('/api/front/product_sale_elements');
        $ids = array_column($collection['hydra:member'] ?? [], 'id');

        self::assertNotContains($pse->getId(), $ids);
        self::assertSame(
            404,
            $this->jsonRequest('GET', '/api/front/product_sale_elements/'.$pse->getId())->getStatusCode(),
        );
    }

    public function testACustomerTheOperationDoesNotNameDoesNotSeeThemEither(): void
    {
        $product = $this->catalogProduct();
        $this->hiddenReservedSaleOn($product, $this->newCustomer());
        $token = $this->authenticateAsCustomer($this->newCustomer('password'));

        $payload = $this->readJson('/api/front/products', $token);

        self::assertNull($this->memberOrNull($payload, $product->getId()));
        self::assertSame(
            404,
            $this->jsonRequest('GET', '/api/front/products/'.$product->getId(), token: $token)->getStatusCode(),
        );
    }

    public function testTheCustomerTheOperationNamesSeesThem(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer('password');
        $this->hiddenReservedSaleOn($product, $customer);
        $token = $this->authenticateAsCustomer($customer);

        $payload = $this->readJson('/api/front/products', $token);

        self::assertNotNull(
            $this->memberOrNull($payload, $product->getId()),
            'The operation is open to this customer, so its products are part of their catalog.',
        );
        self::assertSame(
            200,
            $this->jsonRequest('GET', '/api/front/products/'.$product->getId(), token: $token)->getStatusCode(),
        );
    }

    /**
     * Two hidden operations covering the same product, and a customer named on one
     * of them. Being left out of one private drop is not what hides a product: the
     * operation they are part of is the one that decides.
     */
    public function testACustomerNamedOnOneOfTwoOverlappingHiddenOperationsStillSeesTheProduct(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer('password');
        $this->hiddenReservedSaleOn($product, $customer);
        $this->hiddenReservedSaleOn($product, $this->newCustomer());
        $token = $this->authenticateAsCustomer($customer);

        $payload = $this->readJson('/api/front/products', $token);

        self::assertNotNull(
            $this->memberOrNull($payload, $product->getId()),
            'The operation the customer is named on shows the product, whatever the other one says.',
        );
        self::assertSame(
            200,
            $this->jsonRequest('GET', '/api/front/products/'.$product->getId(), token: $token)->getStatusCode(),
        );

        // The rule is narrowed on the sale elements by the same clause, against the
        // other column: the price has to be reachable too, or the product is a page
        // with nothing to buy on it.
        self::assertSame(
            200,
            $this->jsonRequest(
                'GET',
                '/api/front/product_sale_elements/'.$this->defaultPseFor($product)->getId(),
                token: $token,
            )->getStatusCode(),
        );

        // The same product, read by somebody named on neither of the two.
        $anonymous = $this->readJson('/api/front/products');

        self::assertNull($this->memberOrNull($anonymous, $product->getId()));
        self::assertSame(
            404,
            $this->jsonRequest('GET', '/api/front/products/'.$product->getId())->getStatusCode(),
        );
    }

    /**
     * The setting only ever hides the products of an operation that is reserved:
     * a public operation discounts for everybody, so there is nobody to hide from.
     */
    public function testAPublicOperationHidesNothingEvenWhenTheFlagIsOn(): void
    {
        $product = $this->catalogProduct();
        $sale = $this->runningSale([
            'audienceMode' => Sale::AUDIENCE_MODE_PUBLIC,
            'hideProducts' => true,
        ]);
        $this->createFixtureFactory()->saleProduct($sale, $product);

        $payload = $this->readJson('/api/front/products');

        self::assertNotNull($this->memberOrNull($payload, $product->getId()));
    }

    public function testAReservedOperationThatDoesNotHideItsProductsShowsThemToEverybody(): void
    {
        $product = $this->catalogProduct();
        $factory = $this->createFixtureFactory();
        $sale = $this->runningSale([
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => false,
        ]);
        $factory->saleProduct($sale, $product);
        $factory->saleCustomer($sale, $this->newCustomer());

        $payload = $this->readJson('/api/front/products');

        self::assertNotNull($this->memberOrNull($payload, $product->getId()));
    }

    /**
     * An operation whose window has closed hides nothing, whatever its active flag
     * still says: the flag is only as fresh as the last run of the scheduled command.
     */
    public function testAnOperationWhoseWindowHasClosedHidesNothing(): void
    {
        $product = $this->catalogProduct();
        $sale = $this->hiddenReservedSaleOn($product, $this->newCustomer());
        $sale->setEndDate(new \DateTime('-1 minute'))->save();

        $payload = $this->readJson('/api/front/products');

        self::assertNotNull($this->memberOrNull($payload, $product->getId()));
    }

    /**
     * A shop with no reserved operation at all must pay nothing for the feature:
     * no join, no subquery added to any catalog query, and nothing per product.
     */
    public function testAShopWithoutAnyReservedOperationGetsNoExtraSubquery(): void
    {
        $product = $this->catalogProduct();
        $sale = $this->runningSale(['audienceMode' => Sale::AUDIENCE_MODE_PUBLIC]);
        $this->createFixtureFactory()->saleProduct($sale, $product);
        $this->catalogProduct();
        $this->catalogProduct();

        $payload = [];
        $statements = $this->recordSqlQueries(function () use (&$payload): void {
            $payload = $this->readJson('/api/front/products');
        });

        self::assertNotNull($this->memberOrNull($payload, $product->getId()));

        self::assertSame(
            0,
            \count(array_filter(
                $statements,
                static fn (string $statement): bool => str_contains($statement, 'NOT EXISTS'),
            )),
            'No reserved operation runs, so no catalog query is narrowed.',
        );

        self::assertSame(
            0,
            \count(array_filter(
                $statements,
                static fn (string $statement): bool => str_contains($statement, 'sale_customer'),
            )),
            'Nobody is entitled to anything, so the audience is never read.',
        );

        // The whole bill of the feature on such a shop: the indexed existence check
        // that says there is no reserved operation, asked once for the request.
        self::assertSame(
            1,
            \count(array_filter(
                $statements,
                static fn (string $statement): bool => str_contains($statement, 'FROM `sale`'),
            )),
        );
    }

    private function catalogProduct(): Product
    {
        return $this->createFixtureFactory()->product(
            $this->category,
            $this->taxRule,
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => 100.0, 'title' => 'Reserved catalog product'],
        );
    }

    private function hiddenReservedSaleOn(Product $product, Customer $customer): Sale
    {
        $factory = $this->createFixtureFactory();
        $sale = $this->runningSale([
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => true,
        ]);
        $factory->saleProduct($sale, $product);
        $factory->saleCustomer($sale, $customer);
        $factory->saleOffsetCurrency($sale, $this->currency, 10.0);

        return $sale;
    }

    private function runningSale(array $overrides): Sale
    {
        return $this->createFixtureFactory()->sale($overrides + [
            'active' => true,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
    }

    private function newCustomer(?string $password = null): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer(
            $factory->customerTitle(),
            null === $password ? [] : ['password' => $password],
        );
    }

    private function defaultPseFor(Product $product): ProductSaleElements
    {
        return ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $uri, ?string $token = null): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $token);
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private function memberOrNull(array $payload, ?int $productId): ?array
    {
        foreach ($payload['hydra:member'] ?? [] as $member) {
            if (($member['id'] ?? null) === $productId) {
                return $member;
            }
        }

        return null;
    }
}
