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

namespace Thelia\Tests\Integration\Api;

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Customer as CustomerResource;
use Thelia\Api\Security\CustomerGroupScopeVoter;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The storefront is told the group the shop prices the customer by (the resolver's), so the
 * IRI it reads is one the voter of that group lets through. A guest row, reusable by e-mail,
 * belongs to the default group whatever its column says; the administration reads the column.
 */
final class CustomerGroupResolvedOnTheFrontReadTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    public function testAGuestRowIsReadInTheDefaultGroupAndTheIriOfItIsGranted(): void
    {
        $pro = $this->factory->customerGroup('pro-on-a-guest-row', 15.0);
        $guest = $this->factory->guestCustomer($this->factory->customerTitle());
        $this->writeTheGroupColumn($guest, $pro->getId());
        $default = CustomerGroupQuery::findDefault();

        $resource = $this->read($this->reload($guest), [CustomerResource::GROUP_FRONT_READ_SINGLE]);

        self::assertSame($default->getId(), $resource->getCustomerGroup()?->getId());
        self::assertSame($default->getCode(), $resource->getCustomerGroup()->getCode());
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOnTheGroupOf($guest, $resource), 'the IRI read answers to its bearer');
    }

    public function testAnAccountIsReadInItsOwnGroup(): void
    {
        $pro = $this->factory->customerGroup('pro-account', 15.0);
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]);

        $resource = $this->read($this->reload($customer), [CustomerResource::GROUP_FRONT_READ_SINGLE]);

        self::assertSame($pro->getId(), $resource->getCustomerGroup()?->getId());
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOnTheGroupOf($customer, $resource));
    }

    public function testTheAdministrationStillReadsTheColumnOfTheRow(): void
    {
        $pro = $this->factory->customerGroup('pro-admin', 15.0);
        $guest = $this->factory->guestCustomer($this->factory->customerTitle());
        $this->writeTheGroupColumn($guest, $pro->getId());

        $resource = $this->read($this->reload($guest), [CustomerResource::GROUP_ADMIN_READ, CustomerResource::GROUP_ADMIN_READ_SINGLE]);

        self::assertSame($pro->getId(), $resource->getCustomerGroup()?->getId());
    }

    private function writeTheGroupColumn(Customer $customer, int $groupId): void
    {
        CustomerQuery::create()->filterById($customer->getId())->update(['CustomerGroupId' => $groupId]);
    }

    private function reload(Customer $customer): Customer
    {
        CustomerTableMap::clearInstancePool();
        $this->getService('services_resetter')->reset();

        return CustomerQuery::create()->findPk($customer->getId())
            ?? throw new \LogicException('The customer is gone.');
    }

    /**
     * @param list<string> $groups
     */
    private function read(Customer $customer, array $groups): CustomerResource
    {
        $resource = $this->getService(ApiResourcePropelTransformerService::class)->modelToResource(
            resourceClass: CustomerResource::class,
            propelModel: $customer,
            context: ['groups' => $groups],
        );
        self::assertInstanceOf(CustomerResource::class, $resource);

        return $resource;
    }

    private function voteOnTheGroupOf(Customer $customer, CustomerResource $resource): int
    {
        $token = new UsernamePasswordToken($this->reload($customer), 'front', ['ROLE_CUSTOMER']);

        return $this->getService(CustomerGroupScopeVoter::class)->vote($token, $resource->getCustomerGroup(), [CustomerGroupScopeVoter::SCOPE]);
    }
}
