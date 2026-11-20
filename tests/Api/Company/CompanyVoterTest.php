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

use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Thelia\Api\Security\CompanyVoter;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\Customer;
use Thelia\Tests\Integration\Company\AddressTestCase;

/**
 * What the voter grants a customer, and to whom it grants nothing at all.
 */
final class CompanyVoterTest extends AddressTestCase
{
    private CompanyVoter $voter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->voter = $this->getService(CompanyVoter::class);
    }

    public function testAMemberIsGrantedTheMembershipOfTheirOwnCompanyAndOfNoOther(): void
    {
        $companies = $this->twoCompanies();

        self::assertTrue($this->granted($companies['creator'], CompanyVoter::MEMBER));
        self::assertTrue($this->granted($companies['creator'], CompanyVoter::MEMBER, $companies['first']));
        self::assertFalse($this->granted($companies['creator'], CompanyVoter::MEMBER, $companies['second']));
        self::assertFalse($this->granted($this->customer(), CompanyVoter::MEMBER));
    }

    public function testAPermissionIsGrantedForTheOwnCompanyByItsCodeAndForAGivenCompanyOnly(): void
    {
        $companies = $this->twoCompanies();

        self::assertTrue($this->granted($companies['administratorOfFirst'], CompanyVoter::PERMISSION, CompanyPermission::BUYER_MANAGE));
        self::assertFalse($this->granted($companies['creator'], CompanyVoter::PERMISSION, CompanyPermission::BUYER_MANAGE));
        self::assertFalse($this->granted($companies['administratorOfFirst'], CompanyVoter::PERMISSION, 'teleport'));

        self::assertTrue($this->granted($companies['administratorOfFirst'], CompanyVoter::PERMISSION, [CompanyPermission::BUYER_MANAGE, $companies['first']]));
        self::assertFalse($this->granted($companies['administratorOfFirst'], CompanyVoter::PERMISSION, [CompanyPermission::BUYER_MANAGE, $companies['second']]));
    }

    public function testAnOrderIsGrantedToItsBuyerAndToTheCompanyThatMayReadIt(): void
    {
        $companies = $this->twoCompanies();
        $approver = $this->buyerOf($companies['first'], CompanyRoleCode::APPROVER);
        $order = $this->orderOf($companies['creator'], $companies['first'], $companies['creator']);

        self::assertTrue($this->granted($companies['creator'], CompanyVoter::ORDER_READ, $order));
        self::assertTrue($this->granted($approver, CompanyVoter::ORDER_READ, $order));
        self::assertFalse($this->granted($companies['colleague'], CompanyVoter::ORDER_READ, $order));
        self::assertFalse($this->granted($companies['administratorOfSecond'], CompanyVoter::ORDER_READ, $order));
    }

    public function testAnAddressIsUsedByTheCompanyAndChangedByItsCreatorAndAdministrators(): void
    {
        $companies = $this->twoCompanies();
        $shared = $this->addressOf($companies['creator'], $companies['first']);

        self::assertTrue($this->granted($companies['colleague'], CompanyVoter::ADDRESS_USE, $shared));
        self::assertFalse($this->granted($companies['colleague'], CompanyVoter::ADDRESS_EDIT, $shared));
        self::assertTrue($this->granted($companies['creator'], CompanyVoter::ADDRESS_EDIT, $shared));
        self::assertTrue($this->granted($companies['administratorOfFirst'], CompanyVoter::ADDRESS_EDIT, $shared));
        self::assertFalse($this->granted($companies['administratorOfSecond'], CompanyVoter::ADDRESS_USE, $shared));
    }

    public function testAGuestAnAnonymousCallerAndAnAdministratorAreGrantedNothing(): void
    {
        $companies = $this->twoCompanies();
        $order = $this->orderOf($companies['creator'], $companies['first'], $companies['creator']);
        $guest = $this->guest();
        $guestOrder = $this->orderOf($guest);

        self::assertFalse($this->granted($guest, CompanyVoter::ORDER_READ, $guestOrder));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote(new NullToken(), $order, [CompanyVoter::ORDER_READ]));

        $administrator = $this->fixtures->admin();
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote(new UsernamePasswordToken($administrator, 'admin', $administrator->getRoles()), $order, [CompanyVoter::ORDER_READ]),
        );
    }

    public function testASubjectOfAnotherKindIsNotItsBusiness(): void
    {
        $customer = $this->customer();

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($this->token($customer), new \stdClass(), [CompanyVoter::ORDER_READ]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($this->token($customer), 'x', ['SOMETHING_ELSE']));
    }

    private function granted(Customer $customer, string $attribute, mixed $subject = null): bool
    {
        return VoterInterface::ACCESS_GRANTED === $this->voter->vote($this->token($customer), $subject, [$attribute]);
    }

    private function token(Customer $customer): UsernamePasswordToken
    {
        return new UsernamePasswordToken($customer, 'front', $customer->getRoles());
    }
}
