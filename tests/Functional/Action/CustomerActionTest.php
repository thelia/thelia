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

namespace Thelia\Tests\Functional\Action;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateMinimalEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CustomerQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class CustomerActionTest extends IntegrationTestCase
{
    private EventDispatcherInterface $dispatcher;
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->getService(EventDispatcherInterface::class);
        $this->factory = $this->createFixtureFactory();
    }

    public function testCreateMinimalCustomerPersistsWithHashedPassword(): void
    {
        $title = $this->factory->customerTitle();

        $event = new CustomerCreateOrUpdateMinimalEvent();
        $event
            ->setTitle($title->getId())
            ->setFirstname('Jane')
            ->setLastname('Doe')
            ->setEmail('jane.doe@test.com')
            ->setPassword('secret123');

        $this->dispatcher->dispatch($event, TheliaEvents::CREATE_CUSTOMER_MINIMAL);

        $customer = $event->getCustomer();
        self::assertNotNull($customer);
        self::assertNotNull($customer->getId());

        $reloaded = CustomerQuery::create()->findPk($customer->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Jane', $reloaded->getFirstname());
        self::assertSame('Doe', $reloaded->getLastname());
        self::assertSame('jane.doe@test.com', $reloaded->getEmail());

        self::assertNotSame('secret123', $reloaded->getPassword());
        self::assertTrue(password_verify('secret123', $reloaded->getPassword()));
        self::assertSame('PASSWORD_BCRYPT', $reloaded->getAlgo());
    }

    public function testCreateMinimalCustomerWithDiscount(): void
    {
        $title = $this->factory->customerTitle();

        $event = new CustomerCreateOrUpdateMinimalEvent();
        $event
            ->setTitle($title->getId())
            ->setFirstname('John')
            ->setLastname('Discount')
            ->setEmail('discount@test.com')
            ->setPassword('password')
            ->setDiscount(15.5)
            ->setReseller(true);

        $this->dispatcher->dispatch($event, TheliaEvents::CREATE_CUSTOMER_MINIMAL);

        $customer = $event->getCustomer();
        self::assertNotNull($customer);
        self::assertEqualsWithDelta(15.5, (float) $customer->getDiscount(), 0.01);
        self::assertTrue((bool) $customer->getReseller());
    }

    public function testTransactionRollbackIsolatesTests(): void
    {
        $title = $this->factory->customerTitle();

        $event = new CustomerCreateOrUpdateMinimalEvent();
        $event
            ->setTitle($title->getId())
            ->setFirstname('Isolated')
            ->setLastname('Test')
            ->setEmail('isolated@test.com')
            ->setPassword('password');

        $this->dispatcher->dispatch($event, TheliaEvents::CREATE_CUSTOMER_MINIMAL);

        $id = $event->getCustomer()->getId();
        self::assertNotNull(CustomerQuery::create()->findPk($id));

        // This customer will be rolled back in tearDown().
        // The next test should not see it.
    }

    public function testPreviousTestDataIsRolledBack(): void
    {
        $result = CustomerQuery::create()
            ->filterByEmail('isolated@test.com')
            ->findOne();

        self::assertNull($result, 'Transaction rollback should have removed the customer from the previous test');
    }
}
