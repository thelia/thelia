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

namespace Thelia\Tests\Integration\Api\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Thelia\Api\Resource\CustomerGroup;
use Thelia\Api\Security\CustomerGroupScopeVoter;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The group a customer reads on the front is the one the shop prices it by: the default
 * group for a guest row, whatever is written on the row, and the group the database names
 * today for an account, whatever the object in the token says.
 */
final class CustomerGroupScopeVoterTest extends IntegrationTestCase
{
    public function testAnAccountReadsItsOwnGroupOnly(): void
    {
        $factory = $this->createFixtureFactory();
        $mine = $factory->customerGroup('voter-mine');
        $theirs = $factory->customerGroup('voter-theirs');
        $customer = $factory->customer($factory->customerTitle(), ['customerGroup' => $mine]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($customer, (int) $mine->getId()));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($customer, (int) $theirs->getId()));
    }

    public function testAGuestRowFiledUnderAnotherGroupReadsTheDefaultGroupOnly(): void
    {
        $factory = $this->createFixtureFactory();
        $pro = $factory->customerGroup('voter-guest-pro', discount: 30.0);
        $guest = $factory->guestCustomer($factory->customerTitle());
        $guest->setCustomerGroupId($pro->getId())->save($this->getPropelConnection());
        $defaultGroupId = (int) CustomerGroupQuery::findDefault()->getId();

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($guest, (int) $pro->getId()), 'the group written on a guest row is not its group');
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($guest, $defaultGroupId));
    }

    public function testTheGroupIsReadBackFromTheDatabaseNotFromTheObjectOfTheToken(): void
    {
        $factory = $this->createFixtureFactory();
        $before = $factory->customerGroup('voter-before');
        $after = $factory->customerGroup('voter-after');
        $customer = $factory->customer($factory->customerTitle(), ['customerGroup' => $before]);

        // An administrator moves the account while the token still carries the old object.
        $stale = clone $customer;
        $customer->setCustomerGroupId($after->getId())->save($this->getPropelConnection());

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($stale, (int) $before->getId()));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($stale, (int) $after->getId()));
    }

    public function testNobodyElseIsGranted(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            (new CustomerGroupScopeVoter($this->getService(CustomerGroupResolver::class)))
                ->vote($token, (new CustomerGroup())->setId((int) CustomerGroupQuery::findDefault()->getId()), [CustomerGroupScopeVoter::SCOPE]),
        );
    }

    private function vote(Customer $customer, int $groupId): int
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($customer);

        $this->getService('services_resetter')->reset();

        return (new CustomerGroupScopeVoter($this->getService(CustomerGroupResolver::class)))
            ->vote($token, (new CustomerGroup())->setId($groupId), [CustomerGroupScopeVoter::SCOPE]);
    }
}
