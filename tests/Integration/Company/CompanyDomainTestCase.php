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

namespace Thelia\Tests\Integration\Company;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Company\CompanyFacade;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Domain\Company\Service\CompanyPermissionChecker;
use Thelia\Domain\Company\Service\CompanyRoleResolver;
use Thelia\Model\Address;
use Thelia\Model\Company;
use Thelia\Model\CompanyCustomer;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyRole;
use Thelia\Model\CompanyRoleQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * What the tests of the company domain share: the services built the way the container builds
 * them, the accounts and the companies they place in the database, and the events they listen to.
 */
abstract class CompanyDomainTestCase extends IntegrationTestCase
{
    protected FixtureFactory $fixtures;

    protected CompanyMembershipReader $reader;

    protected CompanyFacade $facade;

    protected CompanyPermissionChecker $checker;

    /** @var array<string, list<object>> the events heard, by name */
    protected array $events = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = $this->createFixtureFactory();
        $this->reader = new CompanyMembershipReader();
        $dispatcher = static::getContainer()->get('event_dispatcher');
        \assert($dispatcher instanceof EventDispatcherInterface);
        $this->facade = new CompanyFacade($dispatcher, new CompanyRoleResolver(), $this->reader);
        $this->checker = new CompanyPermissionChecker($this->reader);

        $this->events = [];

        foreach ([
            'action.company.create',
            'action.company.update',
            'action.company.suspend',
            'action.company.reactivate',
            'action.company.delete',
            'action.company.customer.attach',
            'action.company.customer.detach',
            'action.company.customer.changeRole',
            'action.company.customer.changeLimits',
        ] as $name) {
            $dispatcher->addListener($name, function (object $event) use ($name): void {
                $this->events[$name][] = $event;
            });
        }
    }

    /**
     * An account that can sign in.
     */
    protected function customer(?string $email = null): Customer
    {
        $customer = $this->fixtures->customer($this->fixtures->customerTitle(), null === $email ? [] : ['email' => $email]);
        $customer->setEnable(1)->save($this->getPropelConnection());

        return $customer;
    }

    protected function guest(): Customer
    {
        return $this->fixtures->guestCustomer($this->fixtures->customerTitle());
    }

    protected function company(string $name = 'Acme'): Company
    {
        $company = new Company();
        $company->setName($name);
        $company->save($this->getPropelConnection());

        return $company;
    }

    protected function buyerOf(Company $company, string $roleCode = 'buyer', ?Customer $customer = null, ?string $orderLimit = null, ?string $periodLimit = null, ?string $period = null): Customer
    {
        $customer ??= $this->customer();
        $this->facade->attach($company, $customer, $roleCode, $orderLimit, $periodLimit, $period);

        return $customer;
    }

    /**
     * @param list<string> $permissions
     */
    protected function roleOf(string $code, ?Company $company, array $permissions): CompanyRole
    {
        $role = new CompanyRole();
        $role->setCode($code);
        $role->setCompanyId($company?->getId());
        $role->setPermissions(json_encode($permissions, \JSON_THROW_ON_ERROR));
        $role->setLocale('en_US')->setTitle(ucfirst($code));
        $role->save($this->getPropelConnection());

        return $role;
    }

    protected function sharedRole(string $code): CompanyRole
    {
        return CompanyRoleQuery::create()->filterByCode($code)->filterByCompanyId(null, Criteria::ISNULL)->findOne() ?? throw new \LogicException($code);
    }

    protected function addressOf(Customer $creator, ?Company $sharedWith = null, string $label = 'Address'): Address
    {
        $address = $this->fixtures->address($creator, null, null, ['label' => $label]);
        $address->setCompanyId($sharedWith?->getId());
        $address->save($this->getPropelConnection());

        return $address;
    }

    protected function orderOf(Customer $owner, ?Company $company = null, ?Customer $placedBy = null): Order
    {
        $order = $this->fixtures->order($owner);
        $order->setCompanyId($company?->getId());
        $order->setPlacedByCustomerId($placedBy?->getId());
        $order->save($this->getPropelConnection());

        return $order;
    }

    protected function buyerRow(Customer $customer): ?CompanyCustomer
    {
        return CompanyCustomerQuery::create()->filterByCustomerId($customer->getId())->findOne();
    }
}
