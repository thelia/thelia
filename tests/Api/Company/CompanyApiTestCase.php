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

namespace Thelia\Tests\Api\Company;

use Thelia\Domain\Company\CompanyFacade;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Model\Address;
use Thelia\Model\Company;
use Thelia\Model\CompanyRole;
use Thelia\Model\CompanyRoleQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;
use Thelia\Tests\Api\RateLimit\RateLimitTestEnvironment;

/**
 * What the API tests of the companies share: accounts that can sign in, the companies they
 * belong to, and the orders and addresses the companies hold.
 */
abstract class CompanyApiTestCase extends ApiTestCase
{
    use RateLimitTestEnvironment;

    protected FixtureFactory $fixtures;

    protected CompanyFacade $facade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = $this->createFixtureFactory();
        $this->facade = $this->getService(CompanyFacade::class);
        $this->clearRateLimiterCounters();
    }

    protected function customer(?string $email = null): Customer
    {
        $customer = $this->fixtures->customer($this->fixtures->customerTitle(), null === $email ? [] : ['email' => $email]);
        $customer->setEnable(1)->save($this->getPropelConnection());

        return $customer;
    }

    protected function company(string $name = 'Acme'): Company
    {
        $company = new Company();
        $company->setName($name);
        $company->save($this->getPropelConnection());

        return $company;
    }

    protected function buyerOf(Company $company, string $roleCode = CompanyRoleCode::BUYER, ?string $orderLimit = null, ?string $periodLimit = null, ?string $period = null): Customer
    {
        $customer = $this->customer();
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
        return CompanyRoleQuery::create()->filterByCode($code)->filterByCompanyId(null, \Propel\Runtime\ActiveQuery\Criteria::ISNULL)->findOne() ?? throw new \LogicException($code);
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

    protected function forgetMemberships(): void
    {
        $this->getService(CompanyMembershipReader::class)->reset();
    }

    protected function token(Customer $customer): string
    {
        return $this->authenticateAsCustomer($customer);
    }

    /**
     * @return array<string, mixed>
     */
    protected function body(\Symfony\Component\HttpFoundation\Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<int> the ids of the members of a collection answer
     */
    protected function memberIds(\Symfony\Component\HttpFoundation\Response $response): array
    {
        return array_map(static fn (array $member): int => (int) $member['id'], $this->body($response)['hydra:member'] ?? []);
    }
}
