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

namespace Thelia\Tests\Integration\Api\Service\DataAccess;

use Thelia\Api\Service\DataAccess\ProductSaleElementsAccessService;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\Sale;
use Thelia\Model\TaxRule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;

/**
 * The combination picker of a product page reads its prices from this data
 * access, not from the API resource: it needs every sale element of the product
 * at once, keyed by the attribute values that select it.
 *
 * It is therefore a price path of its own, and the reserved price has to travel
 * on it too — otherwise the page shows one price and the picker another.
 */
final class ProductSaleElementsAccessReservedPriceTest extends IntegrationTestCase
{
    use LogsInAsCustomer;

    private Currency $currency;
    private Category $category;
    private TaxRule $taxRule;
    private ProductSaleElementsAccessService $accessService;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->currency = $factory->currency();
        $this->category = $factory->category();
        $this->taxRule = $factory->taxRule();
        $this->accessService = $this->getService(ProductSaleElementsAccessService::class);
    }

    public function testTheNamedCustomerReadsTheReservedPrice(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer();
        $this->reservedSaleOn($product, $customer);
        $this->loginAsCustomerInSession($customer);

        $saleElement = $this->firstSaleElementOf($product);

        self::assertTrue($saleElement['isPromo']);
        self::assertEqualsWithDelta(90.0, (float) $saleElement['promoUntaxedPrice'], 0.000001);
        // The offset is taken off the taxed price: 100 HT / 120 TTC, minus 10%.
        self::assertEqualsWithDelta(108.0, (float) $saleElement['promoPrice'], 0.000001);
        self::assertEqualsWithDelta(100.0, (float) $saleElement['untaxedPrice'], 0.000001);
    }

    public function testAVisitorReadsTheCatalogPrice(): void
    {
        $product = $this->catalogProduct();
        $this->reservedSaleOn($product, $this->newCustomer());

        $saleElement = $this->firstSaleElementOf($product);

        self::assertFalse($saleElement['isPromo']);
        self::assertEqualsWithDelta(100.0, (float) $saleElement['promoUntaxedPrice'], 0.000001);
    }

    public function testACustomerTheOperationDoesNotNameReadsTheCatalogPrice(): void
    {
        $product = $this->catalogProduct();
        $this->reservedSaleOn($product, $this->newCustomer());
        $this->loginAsCustomerInSession($this->newCustomer());

        self::assertFalse($this->firstSaleElementOf($product)['isPromo']);
    }

    /**
     * The picker is reached by a product id, so it has to answer nothing at all for
     * a product a private drop keeps out of the catalog: the page is 404 for this
     * visitor, and its prices must not be readable behind it.
     */
    public function testAPrivateDropAnswersNoSaleElementAtAll(): void
    {
        $product = $this->catalogProduct();
        $this->reservedSaleOn($product, $this->newCustomer(), hideProducts: true);

        self::assertSame([], $this->saleElementsOf($product));
    }

    public function testTheNamedCustomerStillReadsThePrivateDrop(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer();
        $this->reservedSaleOn($product, $customer, hideProducts: true);
        $this->loginAsCustomerInSession($customer);

        self::assertCount(1, $this->saleElementsOf($product));
    }

    private function catalogProduct(): Product
    {
        return $this->createFixtureFactory()->product(
            $this->category,
            $this->taxRule,
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => 100.0, 'title' => 'Picker product'],
        );
    }

    private function reservedSaleOn(Product $product, Customer $customer, bool $hideProducts = false): Sale
    {
        $factory = $this->createFixtureFactory();
        $sale = $factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => $hideProducts,
            'priceOffsetType' => Sale::OFFSET_TYPE_PERCENTAGE,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
        $factory->saleProduct($sale, $product);
        $factory->saleCustomer($sale, $customer);
        $factory->saleOffsetCurrency($sale, $this->currency, 10.0);

        return $sale;
    }

    private function newCustomer(): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer($factory->customerTitle());
    }

    /**
     * @return array<string, mixed>
     */
    private function firstSaleElementOf(Product $product): array
    {
        $saleElements = $this->saleElementsOf($product);

        self::assertNotSame([], $saleElements, 'The product under test has to be readable, otherwise nothing is measured.');

        return $saleElements[0];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function saleElementsOf(Product $product): array
    {
        return json_decode(
            (string) $this->accessService->psesByProduct($product->getId()),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
    }
}
