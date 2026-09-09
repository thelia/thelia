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

use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Model\Customer;
use Thelia\Model\Sale;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * Who a sale operation is open to, and whether the shop has any reserved
 * operation running at all — the question the cache bypass of step C asks
 * on every page.
 */
final class SaleAudienceCheckerTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private SaleAudienceChecker $checker;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = $this->getService(SaleAudienceChecker::class);
        $this->factory = $this->createFixtureFactory();
    }

    public function testAnOperationOpenToEveryoneEntitlesEveryone(): void
    {
        $sale = $this->factory->sale(['active' => true]);

        self::assertTrue($this->checker->isCustomerEntitled($sale, null), 'a visitor gets a public operation');
        self::assertTrue($this->checker->isCustomerEntitled($sale, $this->newCustomer()));
    }

    public function testAReservedOperationEntitlesNoVisitor(): void
    {
        $sale = $this->reservedSale();

        self::assertFalse($this->checker->isCustomerEntitled($sale, null));
    }

    public function testAReservedOperationEntitlesTheCustomersItNames(): void
    {
        $named = $this->newCustomer();
        $other = $this->newCustomer();
        $sale = $this->reservedSale();
        $this->factory->saleCustomer($sale, $named);

        self::assertTrue($this->checker->isCustomerEntitled($sale, $named));
        self::assertFalse($this->checker->isCustomerEntitled($sale, $other));
    }

    /**
     * Customer groups are US #122. Until the groups are read, an operation targeting
     * them names nobody, and nobody is entitled — which is the safe way round.
     */
    public function testAnOperationTargetingCustomerGroupsEntitlesNobodyYet(): void
    {
        $sale = $this->factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMER_GROUPS,
        ]);

        self::assertFalse($this->checker->isCustomerEntitled($sale, $this->newCustomer()));
    }

    public function testAShopWithNoReservedOperationSaysSo(): void
    {
        $this->factory->sale(['active' => true]);

        self::assertFalse($this->checker->hasActiveReservedSale());
    }

    public function testAnActiveReservedOperationIsReported(): void
    {
        $this->reservedSale();

        self::assertTrue($this->checker->hasActiveReservedSale());
    }

    public function testAnInactiveReservedOperationIsNotReported(): void
    {
        $this->factory->sale([
            'active' => false,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
        ]);

        self::assertFalse($this->checker->hasActiveReservedSale());
    }

    /**
     * The answer is asked for on every page, so it costs one query per request,
     * not one per caller.
     */
    public function testTheAnswerIsMemoisedForTheRequest(): void
    {
        $this->reservedSale();

        $statements = $this->recordSqlQueries(function (): void {
            self::assertTrue($this->checker->hasActiveReservedSale());
            self::assertTrue($this->checker->hasActiveReservedSale());
            self::assertTrue($this->checker->hasActiveReservedSale());
        });

        self::assertSame(1, self::countSqlQueriesSelectingFrom($statements, 'sale'));
    }

    public function testTheEntitledOperationsOfAVisitorAreNone(): void
    {
        $this->reservedSale();

        self::assertSame([], $this->checker->getEntitledReservedSaleIds(null));
    }

    public function testOnlyTheRunningReservedOperationsTheCustomerIsNamedOnAreEntitled(): void
    {
        $customer = $this->newCustomer();

        $running = $this->reservedSale(['startDate' => new \DateTime('-1 day'), 'endDate' => new \DateTime('+1 day')]);
        $this->factory->saleCustomer($running, $customer);

        $overWithoutDates = $this->reservedSale(['endDate' => new \DateTime('-1 hour')]);
        $this->factory->saleCustomer($overWithoutDates, $customer);

        $notStartedYet = $this->reservedSale(['startDate' => new \DateTime('+1 hour')]);
        $this->factory->saleCustomer($notStartedYet, $customer);

        $inactive = $this->factory->sale(['active' => false, 'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS]);
        $this->factory->saleCustomer($inactive, $customer);

        // Running, but somebody else's.
        $someoneElses = $this->reservedSale();
        $this->factory->saleCustomer($someoneElses, $this->newCustomer());

        // Running and open to everyone: not a reserved entitlement.
        $this->factory->sale(['active' => true]);

        self::assertSame([$running->getId()], $this->checker->getEntitledReservedSaleIds($customer));
    }

    /**
     * An operation with neither a start nor an end date runs for as long as its
     * active flag says so.
     */
    public function testAnOperationWithoutDatesIsEntitledWhileItIsActive(): void
    {
        $customer = $this->newCustomer();
        $sale = $this->reservedSale();
        $this->factory->saleCustomer($sale, $customer);

        self::assertSame([$sale->getId()], $this->checker->getEntitledReservedSaleIds($customer));
    }

    public function testTheEntitledOperationsAreMemoisedPerCustomer(): void
    {
        $customer = $this->newCustomer();
        $sale = $this->reservedSale();
        $this->factory->saleCustomer($sale, $customer);

        $statements = $this->recordSqlQueries(function () use ($customer, $sale): void {
            self::assertSame([$sale->getId()], $this->checker->getEntitledReservedSaleIds($customer));
            self::assertSame([$sale->getId()], $this->checker->getEntitledReservedSaleIds($customer));
        });

        self::assertSame(1, self::countSqlQueriesSelectingFrom($statements, 'sale'));
    }

    private function newCustomer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    private function reservedSale(array $overrides = []): Sale
    {
        return $this->factory->sale($overrides + [
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
        ]);
    }
}
