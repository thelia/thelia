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

namespace Thelia\Tests\Integration\Domain\Sale;

use Thelia\Domain\Sale\ReservedSaleVisibility;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\Sale;
use Thelia\Model\TaxRule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;

/**
 * The rule the catalog is narrowed by, on the shared class every caller goes
 * through — the API extensions, the legacy loops and the theme sitemap alike.
 *
 * A product covered by several hidden operations is the case worth spelling out:
 * being left out of one of them is not what hides a product. Being left out of
 * every one of them is.
 */
final class ReservedSaleVisibilityTest extends IntegrationTestCase
{
    use LogsInAsCustomer;

    private ReservedSaleVisibility $visibility;
    private FixtureFactory $factory;
    private Currency $currency;
    private Category $category;
    private TaxRule $taxRule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->visibility = $this->getService(ReservedSaleVisibility::class);
        $this->factory = $this->createFixtureFactory();
        $this->currency = $this->factory->currency();
        $this->category = $this->factory->category();
        $this->taxRule = $this->factory->taxRule();
    }

    /**
     * Two hidden operations cover the same product and the customer is named on
     * one of them. The operation they are part of is what decides: the product is
     * theirs to see, and the one they are not part of does not take it back.
     */
    public function testAProductCoveredByTwoHiddenOperationsIsVisibleToACustomerNamedOnOneOfThem(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer();

        $this->hiddenReservedSaleOn($product, $customer);
        $this->hiddenReservedSaleOn($product, $this->newCustomer());

        $this->loginAsCustomerInSession($customer);
        $this->forgetMemoisedAudience();

        self::assertTrue(
            $this->isVisible($product),
            'An operation the customer is named on shows the product, whatever the other one says.',
        );
    }

    public function testTheSameProductStaysHiddenFromAVisitorNamedOnNeitherOperation(): void
    {
        $product = $this->catalogProduct();

        $this->hiddenReservedSaleOn($product, $this->newCustomer());
        $this->hiddenReservedSaleOn($product, $this->newCustomer());

        self::assertFalse($this->isVisible($product));
    }

    public function testTheSameProductStaysHiddenFromACustomerNamedOnNeitherOperation(): void
    {
        $product = $this->catalogProduct();

        $this->hiddenReservedSaleOn($product, $this->newCustomer());
        $this->hiddenReservedSaleOn($product, $this->newCustomer());

        $this->loginAsCustomerInSession($this->newCustomer());
        $this->forgetMemoisedAudience();

        self::assertFalse($this->isVisible($product));
    }

    /**
     * The entitlement only ever covers the product the operation holds: another
     * product of an operation the customer is not named on stays out of reach.
     */
    public function testTheEntitlementDoesNotSpillOverToTheOtherProductsOfTheOtherOperation(): void
    {
        $shared = $this->catalogProduct();
        $otherProduct = $this->catalogProduct();
        $customer = $this->newCustomer();

        $this->hiddenReservedSaleOn($shared, $customer);
        $someoneElses = $this->hiddenReservedSaleOn($shared, $this->newCustomer());
        $this->factory->saleProduct($someoneElses, $otherProduct);

        $this->loginAsCustomerInSession($customer);
        $this->forgetMemoisedAudience();

        self::assertTrue($this->isVisible($shared));
        self::assertFalse($this->isVisible($otherProduct));
    }

    /**
     * An operation whose window has closed is no operation at all: it neither
     * hides a product nor shows one.
     */
    public function testAClosedOperationNeitherHidesNorShows(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer();

        $entitledButOver = $this->hiddenReservedSaleOn($product, $customer);
        $entitledButOver->setEndDate(new \DateTime('-1 minute'))->save();
        $this->hiddenReservedSaleOn($product, $this->newCustomer());

        $this->loginAsCustomerInSession($customer);
        $this->forgetMemoisedAudience();

        self::assertFalse(
            $this->isVisible($product),
            'The operation that would show the product is over, and the one hiding it is not.',
        );
    }

    private function isVisible(Product $product): bool
    {
        $query = ProductQuery::create()->filterById($product->getId());
        $this->visibility->applyTo($query, ProductTableMap::COL_ID);

        return $query->count() > 0;
    }

    private function catalogProduct(): Product
    {
        return $this->factory->product(
            $this->category,
            $this->taxRule,
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => 100.0, 'title' => 'Reserved catalog product'],
        );
    }

    private function hiddenReservedSaleOn(Product $product, Customer $customer): Sale
    {
        $sale = $this->factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => true,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);
        $this->factory->saleOffsetCurrency($sale, $this->currency, 10.0);

        return $sale;
    }

    private function newCustomer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    /**
     * Both services answer from memory for the whole request, which a test signing
     * somebody in halfway through has to undo by hand.
     */
    private function forgetMemoisedAudience(): void
    {
        $this->getService(SaleAudienceChecker::class)->reset();
        $this->visibility->reset();
    }
}
