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

use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Sale\ReservedSaleProductRule;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\Sale;
use Thelia\Model\TaxRule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The private drops a visitor is out of, and the ones they are part of, for the
 * customer the request is made for.
 */
final class ReservedSaleProductRuleTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private FixtureFactory $factory;
    private Currency $currency;
    private Category $category;
    private TaxRule $taxRule;
    private ?Customer $currentCustomer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->currency = $this->factory->currency();
        $this->category = $this->factory->category();
        $this->taxRule = $this->factory->taxRule();
    }

    public function testAHidingOperationIsHiddenFromAVisitor(): void
    {
        $sale = $this->hidingSaleNaming($this->newCustomer());

        $rule = $this->rule();

        self::assertSame([$sale->getId()], $rule->hiddenSaleIds());
        self::assertSame([], $rule->entitledHidingSaleIds());
    }

    public function testACustomerIsHandedBackTheHidingOperationsTheyAreNamedOn(): void
    {
        $customer = $this->newCustomer();
        $first = $this->hidingSaleNaming($customer);
        $someoneElses = $this->hidingSaleNaming($this->newCustomer());
        $second = $this->hidingSaleNaming($customer);

        $this->currentCustomer = $customer;
        $rule = $this->rule();

        $hidden = $rule->hiddenSaleIds();
        $entitled = $rule->entitledHidingSaleIds();

        self::assertTrue(array_is_list($hidden));
        self::assertTrue(array_is_list($entitled));
        self::assertSame([$someoneElses->getId()], $hidden);
        self::assertEqualsCanonicalizing([$first->getId(), $second->getId()], $entitled);
    }

    public function testAnOperationThatHidesNothingHandsNothingBack(): void
    {
        $customer = $this->newCustomer();
        $hiding = $this->hidingSaleNaming($customer);
        $this->hidingSaleNaming($customer, ['hideProducts' => false]);

        $this->currentCustomer = $customer;

        self::assertSame([$hiding->getId()], $this->rule()->entitledHidingSaleIds());
    }

    /**
     * The active flag is only as fresh as the last run of the scheduled command:
     * the dates are what decides.
     */
    public function testAHidingOperationWhoseEndDateHasPassedHidesNothingEvenWhileStillFlaggedActive(): void
    {
        $sale = $this->hidingSaleNaming($this->newCustomer(), ['endDate' => new \DateTime('-1 minute')]);

        self::assertSame(1, (int) $sale->getActive(), 'the flag is deliberately left on');
        self::assertSame([], $this->rule()->hiddenSaleIds());
    }

    public function testADeactivatedHidingOperationHidesNothingEvenWithinItsDates(): void
    {
        $this->hidingSaleNaming($this->newCustomer(), ['hideProducts' => false]);
        $this->hidingSaleNaming($this->newCustomer(), ['active' => false]);

        self::assertSame([], $this->rule()->hiddenSaleIds());
    }

    public function testAPublicOperationHidesNothingEvenFlaggedToHideItsProducts(): void
    {
        $this->hidingSaleNaming($this->newCustomer(), ['hideProducts' => false]);
        $this->hidingSaleNaming($this->newCustomer(), ['audienceMode' => Sale::AUDIENCE_MODE_PUBLIC]);

        self::assertSame([], $this->rule()->hiddenSaleIds());
    }

    /**
     * The answer is kept for the request, but per customer: somebody signing in
     * halfway through the request does not read the visitor's catalog.
     */
    public function testTheAnswerIsKeptPerCustomer(): void
    {
        $customer = $this->newCustomer();
        $sale = $this->hidingSaleNaming($customer);
        $rule = $this->rule();

        self::assertSame([$sale->getId()], $rule->hiddenSaleIds());

        $this->currentCustomer = $customer;

        self::assertSame([], $rule->hiddenSaleIds());
        self::assertSame([$sale->getId()], $rule->entitledHidingSaleIds());
    }

    /**
     * The first customer of a shop has the ID 1, and a visitor has none at all:
     * neither reads the other's answer.
     */
    public function testOneCustomerDoesNotReadTheAnswerGivenToAnother(): void
    {
        $sale = $this->hidingSaleNaming($this->newCustomer());
        $firstCustomer = (new Customer())->setId(1);

        $checker = new class((int) $sale->getId()) extends SaleAudienceChecker {
            public function __construct(private readonly int $saleTheFirstCustomerIsNamedOn)
            {
                $probe = new CatalogActivityProbe();
                parent::__construct($probe, new CustomerGroupResolver($probe));
            }

            public function getEntitledReservedSaleIds(?Customer $customer): array
            {
                return 1 === $customer?->getId() ? [$this->saleTheFirstCustomerIsNamedOn] : [];
            }
        };
        $rule = new ReservedSaleProductRule($checker, $this->customerProvider());

        $this->currentCustomer = $firstCustomer;
        self::assertSame([], $rule->hiddenSaleIds());

        $this->currentCustomer = null;
        self::assertSame([$sale->getId()], $rule->hiddenSaleIds());
    }

    public function testAskingAgainCostsNoStatement(): void
    {
        $this->hidingSaleNaming($this->newCustomer());
        $rule = $this->rule();
        $rule->hiddenSaleIds();

        $statements = $this->recordSqlQueries(static function () use ($rule): void {
            $rule->hiddenSaleIds();
            $rule->entitledHidingSaleIds();
        });

        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'sale'));
    }

    private function rule(): ReservedSaleProductRule
    {
        $probe = new CatalogActivityProbe();

        return new ReservedSaleProductRule(new SaleAudienceChecker($probe, new CustomerGroupResolver($probe)), $this->customerProvider());
    }

    private function customerProvider(): CurrentCustomerProvider
    {
        $provider = $this->createStub(CurrentCustomerProvider::class);
        $provider->method('getCurrentCustomer')->willReturnCallback(fn (): ?Customer => $this->currentCustomer);

        return $provider;
    }

    private function hidingSaleNaming(Customer $customer, array $overrides = []): Sale
    {
        $sale = $this->factory->sale($overrides + [
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => true,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
        $this->factory->saleProduct($sale, $this->catalogProduct());
        $this->factory->saleCustomer($sale, $customer);

        return $sale;
    }

    private function catalogProduct(): Product
    {
        return $this->factory->product($this->category, $this->taxRule, $this->currency);
    }

    private function newCustomer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }
}
