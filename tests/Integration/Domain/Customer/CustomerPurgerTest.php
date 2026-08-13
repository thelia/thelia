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

namespace Thelia\Tests\Integration\Domain\Customer;

use Thelia\Domain\Customer\Service\CustomerAnonymizer;
use Thelia\Domain\Customer\Service\CustomerPurger;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Order;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class CustomerPurgerTest extends IntegrationTestCase
{
    private FixtureFactory $factory;
    private CustomerPurger $purger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->purger = $this->getService(CustomerPurger::class);
    }

    public function testAnonymizesAnAccountThatNeverOrderedPastTheRetentionPeriod(): void
    {
        $expired = $this->customerCreatedDaysAgo(400);
        $recent = $this->customerCreatedDaysAgo(10);

        self::assertSame(1, $this->purger->anonymizeAccountsWithoutOrder(365));

        self::assertTrue($this->isAnonymized($expired));
        self::assertFalse($this->isAnonymized($recent));
    }

    /**
     * An account backed by orders is backed by an accounting retention
     * obligation, so it ages on its own period, not on the day it was opened.
     */
    public function testLeavesAnOldAccountAloneWhenItHasOrdered(): void
    {
        $customer = $this->customerCreatedDaysAgo(400);
        $this->orderCreatedDaysAgo($customer, 5);

        self::assertSame(0, $this->purger->anonymizeAccountsWithoutOrder(365));

        self::assertFalse($this->isAnonymized($customer));
    }

    public function testAnonymizesAnAccountIdleSinceItsLastOrder(): void
    {
        $idle = $this->customerCreatedDaysAgo(2000);
        $this->orderCreatedDaysAgo($idle, 1500);

        $active = $this->customerCreatedDaysAgo(2000);
        $this->orderCreatedDaysAgo($active, 1500);
        $this->orderCreatedDaysAgo($active, 30);

        self::assertSame(1, $this->purger->anonymizeAccountsAfterLastOrder(365));

        self::assertTrue($this->isAnonymized($idle));
        self::assertFalse($this->isAnonymized($active));
    }

    public function testLeavesAnAccountWithoutOrderOutOfTheAfterLastOrderPeriod(): void
    {
        $customer = $this->customerCreatedDaysAgo(2000);

        self::assertSame(0, $this->purger->anonymizeAccountsAfterLastOrder(365));

        self::assertFalse($this->isAnonymized($customer));
    }

    /**
     * The job runs unattended, night after night: a second pass must find
     * nothing left to do.
     */
    public function testAnAccountIsAnonymizedOnlyOnce(): void
    {
        $this->customerCreatedDaysAgo(400);

        self::assertSame(1, $this->purger->anonymizeAccountsWithoutOrder(365));
        self::assertSame(0, $this->purger->anonymizeAccountsWithoutOrder(365));
    }

    public function testCountingReportsTheSameAccountsWithoutTouchingThem(): void
    {
        $customer = $this->customerCreatedDaysAgo(400);

        self::assertSame(1, $this->purger->countAccountsWithoutOrder(365));
        self::assertFalse($this->isAnonymized($customer), 'A dry run must not erase anything.');
        self::assertSame(1, $this->purger->countAccountsWithoutOrder(365));
    }

    private function customerCreatedDaysAgo(int $days): Customer
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $customer
            ->setCreatedAt(new \DateTime(\sprintf('-%d days', $days)))
            ->save($this->getPropelConnection());

        return $customer;
    }

    private function orderCreatedDaysAgo(Customer $customer, int $days): Order
    {
        $order = $this->factory->order($customer);
        $order
            ->setCreatedAt(new \DateTime(\sprintf('-%d days', $days)))
            ->save($this->getPropelConnection());

        return $order;
    }

    private function isAnonymized(Customer $customer): bool
    {
        $reloaded = CustomerQuery::create()->findPk($customer->getId());
        self::assertNotNull($reloaded);

        if (null === $reloaded->getAnonymizedAt()) {
            return false;
        }

        self::assertSame(CustomerAnonymizer::ANONYMIZED_VALUE, $reloaded->getLastname());

        return true;
    }
}
