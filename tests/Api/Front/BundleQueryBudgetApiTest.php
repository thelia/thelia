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

use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * What the bundles cost the front API: nothing in a shop that has none, one read per page in a
 * shop that has some.
 */
final class BundleQueryBudgetApiTest extends ApiTestCase
{
    use BuildsBundles;
    use RecordsSqlQueries;

    public function testAShopWithoutABundleAsksNothingOfBundlesWhenSaleElementsAreRead(): void
    {
        $factory = $this->createFixtureFactory();
        $products = [$this->component($factory, '10', 3), $this->component($factory, '20', 3), $this->component($factory, '30', 3)];

        $statements = $this->recordSqlQueries(function () use ($products): void {
            self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/front/product_sale_elements'));
            self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/front/product_sale_elements/'.$this->saleElementsOf($products[0])->getId()));
            self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/front/products'));
        });

        self::assertSame([], self::bundleStatements($statements));
    }

    public function testAShopWithoutABundleAsksNothingOfBundlesWhenASimpleLineIsAdded(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer, ['currency' => Currency::getDefaultCurrency()]);
        $product = $this->component($factory, '10', 3);
        $token = $this->authenticateAsCustomer($customer);

        $statements = $this->recordSqlQueries(function () use ($cart, $product, $token): void {
            $response = $this->jsonRequest('POST', '/api/front/cart_items', [
                'cart' => '/api/front/carts/'.$cart->getId(),
                'productSaleElements' => '/api/front/product_sale_elements/'.$this->saleElementsOf($product)->getId(),
                'quantity' => 1,
            ], $token);
            self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        });

        self::assertSame([], self::bundleStatements($statements));
    }

    public function testAShopWithBundlesReadsThemOncePerPageWhateverItsSize(): void
    {
        $factory = $this->createFixtureFactory();
        $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));
        $simple = array_map(fn (int $price): Product => $this->component($factory, (string) $price, 3), range(10, 70, 10));

        // The sale elements come by id: the bike, the helmet, the lock and the bundle, then the simple products.
        $small = $this->bundleReadsOf('/api/front/product_sale_elements?itemsPerPage=4');
        $large = $this->bundleReadsOf('/api/front/product_sale_elements?itemsPerPage='.(4 + \count($simple)));

        self::assertGreaterThan(0, $small, 'The page of a shop that has a bundle looks the bundles up.');
        self::assertSame($small, $large, 'Reads by page: seven more products on the page cost no read more.');
    }

    private function bundleReadsOf(string $uri): int
    {
        return \count(self::bundleStatements($this->recordSqlQueries(function () use ($uri): void {
            self::assertJsonResponseSuccessful($this->jsonRequest('GET', $uri));
        })));
    }

    /**
     * @param list<string> $statements
     *
     * @return list<string> the statements asking which products are bundles, or reading the items of the bundles
     */
    private static function bundleStatements(array $statements): array
    {
        return array_values(array_filter(
            $statements,
            static fn (string $statement): bool => 1 === preg_match('/is_bundle`?\s*=|FROM `product_bundle_item`/i', $statement),
        ));
    }
}
