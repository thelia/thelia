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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\Customer\CustomerGroupChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\Exception\UnknownCustomerGroupException;
use Thelia\Domain\Customer\Service\CustomerGroupAssignment;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * Whoever moves a customer to another group (the back office, the API, a module, the account
 * events) goes through one service: the group must exist, the move is written and announced
 * once, and nothing is raised when nothing moved.
 */
final class CustomerGroupAssignmentServiceTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private CustomerGroupAssignment $assignment;

    /** @var list<CustomerGroupChangedEvent> */
    private array $heard = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->assignment = $this->getService(CustomerGroupAssignment::class);

        $this->getService(EventDispatcherInterface::class)->addListener(
            TheliaEvents::CUSTOMER_GROUP_CHANGED,
            function (CustomerGroupChangedEvent $event): void {
                $this->heard[] = $event;
            },
        );
    }

    public function testAMoveIsWrittenAndAnnouncedWithTheTwoGroups(): void
    {
        $default = CustomerGroupQuery::findDefault();
        $pro = $this->factory->customerGroup('pro-assigned');
        $customer = $this->factory->customer($this->factory->customerTitle());

        self::assertTrue($this->assignment->assign($customer, (int) $pro->getId()));

        self::assertSame($pro->getId(), $this->stored($customer)->getCustomerGroupId());
        self::assertCount(1, $this->heard);
        self::assertSame($customer->getId(), $this->heard[0]->getCustomer()->getId());
        self::assertSame($default->getId(), $this->heard[0]->getPreviousCustomerGroupId());
        self::assertSame($pro->getId(), $this->heard[0]->getCustomerGroupId());
    }

    public function testTheMoveLeavesAVersionOfTheCustomer(): void
    {
        $pro = $this->factory->customerGroup('pro-versioned');
        $customer = $this->factory->customer($this->factory->customerTitle());
        $before = $customer->getVersion();

        $this->assignment->assign($customer, (int) $pro->getId());

        self::assertGreaterThan($before, $this->stored($customer)->getVersion());
    }

    public function testAnUnknownGroupIsRefusedBeforeAnythingIsWritten(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $groupId = $customer->getCustomerGroupId();

        try {
            $this->assignment->assign($customer, 999999);
            self::fail('A customer was put in a group the shop does not have.');
        } catch (UnknownCustomerGroupException) {
            self::assertSame($groupId, $this->stored($customer)->getCustomerGroupId());
            self::assertSame([], $this->heard);
        }
    }

    public function testAMoveToTheGroupTheCustomerIsInWritesAndAnnouncesNothing(): void
    {
        $pro = $this->factory->customerGroup('pro-already');
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]);
        $version = $customer->getVersion();

        self::assertFalse($this->assignment->assign($customer, (int) $pro->getId()));

        self::assertSame([], $this->heard);
        self::assertSame($version, $this->stored($customer)->getVersion());
    }

    public function testACustomerNotStoredYetIsCreatedInItsGroupNotMovedToIt(): void
    {
        $this->expectException(\LogicException::class);

        $this->assignment->assign(new Customer(), (int) CustomerGroupQuery::findDefault()->getId());
    }

    public function testTheGroupTheResolverRememberedForTheRequestIsForgotten(): void
    {
        $pro = $this->factory->customerGroup('pro-remembered');
        $customer = $this->factory->customer($this->factory->customerTitle());
        $resolver = $this->getService(CustomerGroupResolver::class);
        self::assertSame(CustomerGroupQuery::findDefault()->getId(), $resolver->groupIdOf($customer));

        $this->assignment->assign($customer, (int) $pro->getId());

        self::assertSame($pro->getId(), $resolver->groupIdOf($customer));
    }

    public function testTheAccountEventMovesThroughTheServiceAndAnnouncesIt(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $pro = $this->factory->customerGroup('pro-by-event');

        $this->dispatchUpdate($customer, (int) $pro->getId());

        self::assertSame($pro->getId(), $this->stored($customer)->getCustomerGroupId());
        self::assertCount(1, $this->heard);
    }

    public function testTheAccountEventWithAnUnknownGroupWritesNothingOfTheProfile(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $firstname = $customer->getFirstname();

        try {
            $this->dispatchUpdate($customer, 999999);
            self::fail('The event with an unknown group went through.');
        } catch (UnknownCustomerGroupException) {
            self::assertSame($firstname, $this->stored($customer)->getFirstname());
            self::assertSame([], $this->heard);
        }
    }

    public function testACustomerCreatedInAGroupIsNotAnnouncedAsMoved(): void
    {
        $pro = $this->factory->customerGroup('pro-created');
        $event = $this->updateEvent('created.in.group@test.com', (int) $pro->getId());

        $this->getService(EventDispatcherInterface::class)->dispatch($event, TheliaEvents::CUSTOMER_CREATEACCOUNT);

        self::assertSame($pro->getId(), $this->stored($event->getCustomer())->getCustomerGroupId());
        self::assertSame([], $this->heard);
    }

    public function testAnUpdateWithoutGroupAnnouncesNothing(): void
    {
        $pro = $this->factory->customerGroup('pro-kept');
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]);

        $event = $this->updateEvent($customer->getEmail());
        $event->setCustomer($customer);
        $this->getService(EventDispatcherInterface::class)->dispatch($event, TheliaEvents::CUSTOMER_UPDATEACCOUNT);

        self::assertSame([], $this->heard);
        self::assertSame($pro->getId(), $this->stored($customer)->getCustomerGroupId());
    }

    private function dispatchUpdate(Customer $customer, int $customerGroupId): void
    {
        $event = $this->updateEvent($customer->getEmail(), $customerGroupId);
        $event->setCustomer($customer);

        $this->getService(EventDispatcherInterface::class)->dispatch($event, TheliaEvents::CUSTOMER_UPDATEACCOUNT);
    }

    private function updateEvent(string $email, ?int $customerGroupId = null): CustomerCreateOrUpdateEvent
    {
        $event = new CustomerCreateOrUpdateEvent(
            title: $this->factory->customerTitle()->getId(),
            firstname: 'Ada',
            lastname: 'Admin',
            address1: '1 rue des Lilas',
            zipcode: '75001',
            city: 'Paris',
            country: (string) $this->factory->country()->getId(),
            email: $email,
            password: 'secret123',
            reseller: false,
            customerGroupId: $customerGroupId,
        );
        $event->setNotifyCustomerOfAccountCreation(false);
        $event->setNotifyCustomerOfAccountModification(false);

        return $event;
    }

    private function stored(Customer $customer): Customer
    {
        CustomerTableMap::clearInstancePool();

        return CustomerQuery::create()->findPk($customer->getId())
            ?? throw new \LogicException('The customer is gone.');
    }
}
