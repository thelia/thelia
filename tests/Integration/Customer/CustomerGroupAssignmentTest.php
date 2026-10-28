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

namespace Thelia\Tests\Integration\Customer;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerAnonymizeEvent;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateMinimalEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\DTO\CustomerRegisterDTO;
use Thelia\Domain\Customer\Service\CustomerRegistrationService;
use Thelia\Domain\Customer\Service\CustomerUpdateService;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The group of a customer is an administrator's decision: the back-office events carry it, the account the
 * customer opens or edits by themselves never does.
 */
final class CustomerGroupAssignmentTest extends IntegrationTestCase
{
    private EventDispatcherInterface $dispatcher;
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->getService(EventDispatcherInterface::class);
        $this->factory = $this->createFixtureFactory();
    }

    public function testAnAdminCreatesACustomerInAGroup(): void
    {
        $group = $this->factory->customerGroup('pro');

        $event = $this->createEvent('admin.created@test.com', $group->getId());
        $this->dispatcher->dispatch($event, TheliaEvents::CUSTOMER_CREATEACCOUNT);

        self::assertSame($group->getId(), $this->reload($event->getCustomer()->getId())->getCustomerGroupId());
    }

    public function testAnAdminCreatesACustomerWithoutGroupInTheDefaultGroup(): void
    {
        $event = $this->createEvent('admin.created.default@test.com');
        $this->dispatcher->dispatch($event, TheliaEvents::CUSTOMER_CREATEACCOUNT);

        self::assertSame(CustomerGroupQuery::findDefault()->getId(), $this->reload($event->getCustomer()->getId())->getCustomerGroupId());
    }

    public function testAnAdminMovesACustomerToAnotherGroup(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $group = $this->factory->customerGroup('wholesale');

        $event = $this->createEvent($customer->getEmail(), $group->getId());
        $event->setCustomer($customer);
        $this->dispatcher->dispatch($event, TheliaEvents::CUSTOMER_UPDATEACCOUNT);

        self::assertSame($group->getId(), $this->reload($customer->getId())->getCustomerGroupId());
    }

    public function testAnUpdateWithoutGroupKeepsTheGroupOfTheCustomer(): void
    {
        $group = $this->factory->customerGroup('keeper');
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);

        $event = $this->createEvent($customer->getEmail());
        $event->setCustomer($customer);
        $this->dispatcher->dispatch($event, TheliaEvents::CUSTOMER_UPDATEACCOUNT);

        self::assertSame($group->getId(), $this->reload($customer->getId())->getCustomerGroupId());
    }

    public function testAMinimalAccountCanBeCreatedInAGroupByTheModel(): void
    {
        $group = $this->factory->customerGroup('direct');

        $customer = new \Thelia\Model\Customer();
        $customer->createOrUpdateWithoutAddress(
            titleId: $this->factory->customerTitle()->getId(),
            firstname: 'Direct',
            lastname: 'Model',
            email: 'direct.model@test.com',
            plainPassword: 'secret123',
            customerGroupId: $group->getId(),
        );

        self::assertSame($group->getId(), $this->reload($customer->getId())->getCustomerGroupId());
    }

    public function testTheFrontRegistrationGivesTheDefaultGroup(): void
    {
        $customer = $this->getService(CustomerRegistrationService::class)->registerCustomer(
            new CustomerRegisterDTO(firstname: 'Front', lastname: 'Signup', email: 'front.signup@test.com', password: 'secret123'),
        );

        self::assertSame(CustomerGroupQuery::findDefault()->getId(), $this->reload($customer->getId())->getCustomerGroupId());
    }

    public function testAGroupPassedWithTheFrontRegistrationDataIsIgnored(): void
    {
        $group = $this->factory->customerGroup('sneaky');

        $event = new CustomerCreateOrUpdateMinimalEvent();
        $event
            ->bindArray(['customerGroupId' => $group->getId(), 'customer_group_id' => $group->getId(), 'customerGroup' => $group->getId()])
            ->setTitle($this->factory->customerTitle()->getId())
            ->setFirstname('Sneaky')
            ->setLastname('Signup')
            ->setEmail('sneaky.signup@test.com')
            ->setPassword('secret123');
        $this->dispatcher->dispatch($event, TheliaEvents::CREATE_CUSTOMER_MINIMAL);

        self::assertSame(CustomerGroupQuery::findDefault()->getId(), $this->reload($event->getCustomer()->getId())->getCustomerGroupId());
    }

    public function testTheFrontRegistrationDataCarriesNoGroup(): void
    {
        foreach (array_keys((new CustomerRegisterDTO())->toArray()) as $field) {
            self::assertStringNotContainsStringIgnoringCase('group', $field);
        }
    }

    public function testAProfileUpdateByTheCustomerKeepsTheirGroup(): void
    {
        $group = $this->factory->customerGroup('member');
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);

        $this->getService(CustomerUpdateService::class)->updateCustomer(
            new CustomerRegisterDTO(firstname: 'Renamed', lastname: 'Member', email: $customer->getEmail()),
            $customer,
        );

        $reloaded = $this->reload($customer->getId());
        self::assertSame('Renamed', $reloaded->getFirstname());
        self::assertSame($group->getId(), $reloaded->getCustomerGroupId());
    }

    public function testAnonymizingKeepsTheGroupOfTheCustomer(): void
    {
        $group = $this->factory->customerGroup('erased');
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);

        $this->dispatcher->dispatch(new CustomerAnonymizeEvent($customer), TheliaEvents::CUSTOMER_ANONYMIZE);

        $reloaded = $this->reload($customer->getId());
        self::assertNotNull($reloaded->getAnonymizedAt());
        self::assertSame($group->getId(), $reloaded->getCustomerGroupId());
    }

    private function createEvent(string $email, ?int $customerGroupId = null): CustomerCreateOrUpdateEvent
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

    private function reload(int $customerId): \Thelia\Model\Customer
    {
        return CustomerQuery::create()->findPk($customerId);
    }
}
