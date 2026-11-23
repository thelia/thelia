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

namespace Thelia\Tests\Unit\Domain\Company;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Company\DTO\CompanyMembership;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Domain\Company\Service\CompanyPermissionChecker;
use Thelia\Model\Address;
use Thelia\Model\Order;

/**
 * The checker is the one judge of what a buyer may do, so each role the shop ships is tried against
 * each permission, then what it says about the orders and the addresses of two companies is tried
 * in both directions.
 */
final class CompanyPermissionCheckerTest extends TestCase
{
    private const int FIRST_COMPANY = 1;
    private const int SECOND_COMPANY = 2;

    private const int BUYER_A = 10;
    private const int BUYER_B = 11;
    private const int APPROVER = 12;
    private const int ADMINISTRATOR = 13;
    private const int OTHER_ADMINISTRATOR = 20;
    private const int WITHOUT_COMPANY = 99;

    private const array ADMINISTRATOR_PERMISSIONS = [
        CompanyPermission::ORDER_PLACE,
        CompanyPermission::ORDER_VIEW_OWN,
        CompanyPermission::ORDER_VIEW_COMPANY,
        CompanyPermission::ADDRESS_MANAGE_SHARED,
        CompanyPermission::BUYER_MANAGE,
        CompanyPermission::COMPANY_EDIT,
    ];

    private const array BUYER_PERMISSIONS = [
        CompanyPermission::ORDER_PLACE,
        CompanyPermission::ORDER_VIEW_OWN,
    ];

    private const array APPROVER_PERMISSIONS = [
        CompanyPermission::ORDER_VIEW_OWN,
        CompanyPermission::ORDER_VIEW_COMPANY,
        CompanyPermission::ORDER_APPROVE,
    ];

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function deliveredRoles(): iterable
    {
        yield 'administrator' => [CompanyRoleCode::ADMINISTRATOR, self::ADMINISTRATOR_PERMISSIONS];
        yield 'buyer' => [CompanyRoleCode::BUYER, self::BUYER_PERMISSIONS];
        yield 'approver' => [CompanyRoleCode::APPROVER, self::APPROVER_PERMISSIONS];
    }

    /**
     * @param list<string> $granted
     */
    #[DataProvider('deliveredRoles')]
    public function testEachDeliveredRoleHoldsItsPermissionsAndNoOther(string $roleCode, array $granted): void
    {
        $checker = $this->checker([42 => $this->membership(42, self::FIRST_COMPANY, $roleCode, $granted)]);

        foreach (array_keys(CompanyPermission::labels()) as $permission) {
            self::assertSame(
                \in_array($permission, $granted, true),
                $checker->can(42, $permission, self::FIRST_COMPANY),
                \sprintf('%s and %s', $roleCode, $permission),
            );
            self::assertSame(\in_array($permission, $granted, true), $checker->can(42, $permission), 'Without a company named, the company of the buyer is meant.');
        }
    }

    public function testARoleOfOneCompanyGrantsOnlyWhatItListsAndAnUnknownPermissionIsRefused(): void
    {
        $checker = $this->checker([42 => $this->membership(42, self::FIRST_COMPANY, 'purchaser', [CompanyPermission::ORDER_PLACE, 'quote.request'])]);

        self::assertTrue($checker->can(42, CompanyPermission::ORDER_PLACE));
        self::assertTrue($checker->can(42, 'quote.request'), 'A permission a module declares is judged like the core ones.');
        self::assertFalse($checker->can(42, CompanyPermission::ORDER_VIEW_OWN));
        self::assertFalse($checker->can(42, 'unknown.permission'));
    }

    public function testACustomerWithoutACompanyPlacesOrdersAndReadsItsOwnAndNothingElse(): void
    {
        $checker = $this->checker([]);

        self::assertTrue($checker->can(self::WITHOUT_COMPANY, CompanyPermission::ORDER_PLACE));
        self::assertTrue($checker->can(self::WITHOUT_COMPANY, CompanyPermission::ORDER_VIEW_OWN));

        foreach ([
            CompanyPermission::ORDER_VIEW_COMPANY,
            CompanyPermission::ORDER_APPROVE,
            CompanyPermission::ADDRESS_MANAGE_SHARED,
            CompanyPermission::BUYER_MANAGE,
            CompanyPermission::COMPANY_EDIT,
        ] as $permission) {
            self::assertFalse($checker->can(self::WITHOUT_COMPANY, $permission), $permission);
        }
    }

    public function testAnAccountWithoutACompanyHoldsNothingInACompanyThatIsNamed(): void
    {
        $checker = $this->checker([]);

        self::assertFalse($checker->can(self::WITHOUT_COMPANY, CompanyPermission::ORDER_PLACE, self::FIRST_COMPANY));
        self::assertFalse($checker->can(self::WITHOUT_COMPANY, CompanyPermission::ORDER_VIEW_OWN, self::FIRST_COMPANY));
    }

    public function testABuyerHoldsNothingInAnotherCompany(): void
    {
        $checker = $this->checkerOfTwoCompanies();

        self::assertTrue($checker->can(self::ADMINISTRATOR, CompanyPermission::BUYER_MANAGE, self::FIRST_COMPANY));
        self::assertFalse($checker->can(self::ADMINISTRATOR, CompanyPermission::BUYER_MANAGE, self::SECOND_COMPANY));
        self::assertFalse($checker->can(self::OTHER_ADMINISTRATOR, CompanyPermission::BUYER_MANAGE, self::FIRST_COMPANY));
    }

    public function testASuspendedCompanyPlacesNoOrderForAnyOfItsBuyersButTheyKeepReading(): void
    {
        $checker = $this->checker([
            self::BUYER_A => $this->membership(self::BUYER_A, self::FIRST_COMPANY, CompanyRoleCode::BUYER, self::BUYER_PERMISSIONS, companyActive: false),
            self::ADMINISTRATOR => $this->membership(self::ADMINISTRATOR, self::FIRST_COMPANY, CompanyRoleCode::ADMINISTRATOR, self::ADMINISTRATOR_PERMISSIONS, companyActive: false),
        ]);

        self::assertFalse($checker->can(self::BUYER_A, CompanyPermission::ORDER_PLACE));
        self::assertFalse($checker->can(self::ADMINISTRATOR, CompanyPermission::ORDER_PLACE, self::FIRST_COMPANY));
        self::assertTrue($checker->can(self::BUYER_A, CompanyPermission::ORDER_VIEW_OWN));
        self::assertTrue($checker->can(self::ADMINISTRATOR, CompanyPermission::ORDER_VIEW_COMPANY));
        self::assertTrue($checker->can(self::ADMINISTRATOR, CompanyPermission::BUYER_MANAGE), 'The administrators can still run the company, and suspend or reactivate it.');
    }

    public function testAnOrderIsSeenByItsBuyerTheApproverAndTheAdministratorOfItsCompanyAndNobodyElse(): void
    {
        $checker = $this->checkerOfTwoCompanies();
        $order = $this->order(placedBy: self::BUYER_A, company: self::FIRST_COMPANY);

        self::assertTrue($checker->canAccessOrder(self::BUYER_A, $order), 'The buyer who placed it.');
        self::assertTrue($checker->canAccessOrder(self::APPROVER, $order), 'The approver reads the orders of the company.');
        self::assertTrue($checker->canAccessOrder(self::ADMINISTRATOR, $order), 'The administrator too.');

        self::assertFalse($checker->canAccessOrder(self::BUYER_B, $order), 'A colleague without the permission does not.');
        self::assertFalse($checker->canAccessOrder(self::OTHER_ADMINISTRATOR, $order), 'The administrator of another company does not.');
        self::assertFalse($checker->canAccessOrder(self::WITHOUT_COMPANY, $order), 'Nor does an account with no company.');
    }

    public function testTheOrderOfTheOtherCompanyIsRefusedTheSameWayInTheOtherDirection(): void
    {
        $checker = $this->checkerOfTwoCompanies();
        $order = $this->order(placedBy: self::OTHER_ADMINISTRATOR, company: self::SECOND_COMPANY);

        self::assertTrue($checker->canAccessOrder(self::OTHER_ADMINISTRATOR, $order));
        self::assertFalse($checker->canAccessOrder(self::ADMINISTRATOR, $order));
        self::assertFalse($checker->canAccessOrder(self::APPROVER, $order));
        self::assertFalse($checker->canAccessOrder(self::BUYER_A, $order));
    }

    public function testAnOrderWithoutACompanyIsSeenByItsBuyerOnly(): void
    {
        $checker = $this->checkerOfTwoCompanies();
        $order = $this->order(placedBy: self::WITHOUT_COMPANY, company: null);

        self::assertTrue($checker->canAccessOrder(self::WITHOUT_COMPANY, $order));
        self::assertFalse($checker->canAccessOrder(self::ADMINISTRATOR, $order), 'An administrator does not read the orders a customer placed for themselves.');
        self::assertFalse($checker->canAccessOrder(self::BUYER_A, $order));
    }

    public function testWithoutTheAccountThatPlacedItTheOwnerOfTheOrderIsItsBuyer(): void
    {
        $checker = $this->checkerOfTwoCompanies();
        $order = $this->order(placedBy: null, company: self::FIRST_COMPANY, owner: self::BUYER_A);

        self::assertTrue($checker->canAccessOrder(self::BUYER_A, $order));
        self::assertFalse($checker->canAccessOrder(self::BUYER_B, $order));
        self::assertTrue($checker->canAccessOrder(self::ADMINISTRATOR, $order));
    }

    public function testABuyerWhoLeftKeepsReadingTheOrdersTheyPlacedAndLosesTheOnesOfTheCompany(): void
    {
        $checker = $this->checker([]);
        $own = $this->order(placedBy: self::BUYER_A, company: self::FIRST_COMPANY);
        $colleague = $this->order(placedBy: self::BUYER_B, company: self::FIRST_COMPANY);

        self::assertTrue($checker->canAccessOrder(self::BUYER_A, $own), 'The history stays with the account that placed the order.');
        self::assertFalse($checker->canAccessOrder(self::BUYER_A, $colleague));
    }

    public function testARoleWithoutTheRightToReadItsOwnOrdersDoesNotReadThem(): void
    {
        $checker = $this->checker([42 => $this->membership(42, self::FIRST_COMPANY, 'purchaser', [CompanyPermission::ORDER_PLACE])]);

        self::assertFalse($checker->canAccessOrder(42, $this->order(placedBy: 42, company: self::FIRST_COMPANY)));
    }

    public function testASuspendedCompanyKeepsItsOrdersReadable(): void
    {
        $checker = $this->checker([
            self::ADMINISTRATOR => $this->membership(self::ADMINISTRATOR, self::FIRST_COMPANY, CompanyRoleCode::ADMINISTRATOR, self::ADMINISTRATOR_PERMISSIONS, companyActive: false),
        ]);

        self::assertTrue($checker->canAccessOrder(self::ADMINISTRATOR, $this->order(placedBy: self::BUYER_A, company: self::FIRST_COMPANY)));
    }

    public function testAnAddressSharedWithTheCompanyIsUsedByItsBuyersAndChangedByItsAdministratorsOnly(): void
    {
        $checker = $this->checkerOfTwoCompanies();
        $warehouse = $this->address(creator: self::BUYER_A, company: self::FIRST_COMPANY);
        $home = $this->address(creator: self::BUYER_A, company: null);

        // The creator holds both.
        self::assertTrue($checker->canUseAddress(self::BUYER_A, $warehouse));
        self::assertTrue($checker->canEditAddress(self::BUYER_A, $warehouse));
        self::assertTrue($checker->canUseAddress(self::BUYER_A, $home));
        self::assertTrue($checker->canEditAddress(self::BUYER_A, $home));

        // A colleague without the permission: the shared one to use, never to change; the home none.
        self::assertTrue($checker->canUseAddress(self::BUYER_B, $warehouse));
        self::assertFalse($checker->canEditAddress(self::BUYER_B, $warehouse));
        self::assertFalse($checker->canUseAddress(self::BUYER_B, $home));
        self::assertFalse($checker->canEditAddress(self::BUYER_B, $home));

        // The administrator may change the shared one, and has no claim on the personal one.
        self::assertTrue($checker->canUseAddress(self::ADMINISTRATOR, $warehouse));
        self::assertTrue($checker->canEditAddress(self::ADMINISTRATOR, $warehouse));
        self::assertFalse($checker->canUseAddress(self::ADMINISTRATOR, $home));
        self::assertFalse($checker->canEditAddress(self::ADMINISTRATOR, $home));

        // Another company, or none, reaches neither.
        foreach ([self::OTHER_ADMINISTRATOR, self::WITHOUT_COMPANY] as $stranger) {
            self::assertFalse($checker->canUseAddress($stranger, $warehouse), (string) $stranger);
            self::assertFalse($checker->canEditAddress($stranger, $warehouse), (string) $stranger);
        }
    }

    public function testAnAddressSharedWithTheOtherCompanyIsRefusedTheSameWayInTheOtherDirection(): void
    {
        $checker = $this->checkerOfTwoCompanies();
        $address = $this->address(creator: self::OTHER_ADMINISTRATOR, company: self::SECOND_COMPANY);

        self::assertTrue($checker->canUseAddress(self::OTHER_ADMINISTRATOR, $address));
        self::assertTrue($checker->canEditAddress(self::OTHER_ADMINISTRATOR, $address));
        self::assertFalse($checker->canUseAddress(self::ADMINISTRATOR, $address));
        self::assertFalse($checker->canEditAddress(self::ADMINISTRATOR, $address));
        self::assertFalse($checker->canUseAddress(self::BUYER_A, $address));
    }

    public function testAdministratorWhoseRoleLostTheSharedAddressesPermissionDoesNotChangeThem(): void
    {
        $checker = $this->checker([
            42 => $this->membership(42, self::FIRST_COMPANY, 'reader', [CompanyPermission::ORDER_VIEW_OWN]),
        ]);

        $address = $this->address(creator: self::BUYER_A, company: self::FIRST_COMPANY);

        self::assertTrue($checker->canUseAddress(42, $address));
        self::assertFalse($checker->canEditAddress(42, $address));
    }

    public function testTheCompanyWhoseObjectsABuyerSeesIsTheirOwnWhenTheirRoleHoldsThePermission(): void
    {
        $checker = $this->checkerOfTwoCompanies();

        self::assertSame(self::FIRST_COMPANY, $checker->visibleCompanyIdFor(self::ADMINISTRATOR, CompanyPermission::ORDER_VIEW_COMPANY));
        self::assertSame(self::FIRST_COMPANY, $checker->visibleCompanyIdFor(self::APPROVER, CompanyPermission::ORDER_VIEW_COMPANY));
        self::assertSame(self::SECOND_COMPANY, $checker->visibleCompanyIdFor(self::OTHER_ADMINISTRATOR, CompanyPermission::ORDER_VIEW_COMPANY));
        self::assertNull($checker->visibleCompanyIdFor(self::BUYER_A, CompanyPermission::ORDER_VIEW_COMPANY));
        self::assertNull($checker->visibleCompanyIdFor(self::WITHOUT_COMPANY, CompanyPermission::ORDER_VIEW_COMPANY));
        self::assertNull($checker->visibleCompanyIdFor(self::WITHOUT_COMPANY, CompanyPermission::ORDER_VIEW_OWN), 'No company, nothing to filter by.');
    }

    public function testASuspendedCompanyIsNoLongerTheOneOrdersArePlacedFor(): void
    {
        $checker = $this->checker([
            self::BUYER_A => $this->membership(self::BUYER_A, self::FIRST_COMPANY, CompanyRoleCode::BUYER, self::BUYER_PERMISSIONS, companyActive: false),
        ]);

        self::assertNull($checker->visibleCompanyIdFor(self::BUYER_A, CompanyPermission::ORDER_PLACE));
        self::assertSame(self::FIRST_COMPANY, $checker->visibleCompanyIdFor(self::BUYER_A, CompanyPermission::ORDER_VIEW_OWN));
    }

    /**
     * @param array<int, CompanyMembership> $memberships by customer id
     */
    private function checker(array $memberships): CompanyPermissionChecker
    {
        $reader = $this->createMock(CompanyMembershipReader::class);
        $reader->method('membershipOf')->willReturnCallback(
            static fn (mixed $customer): ?CompanyMembership => $memberships[$customer] ?? null,
        );

        return new CompanyPermissionChecker($reader);
    }

    private function checkerOfTwoCompanies(): CompanyPermissionChecker
    {
        return $this->checker([
            self::BUYER_A => $this->membership(self::BUYER_A, self::FIRST_COMPANY, CompanyRoleCode::BUYER, self::BUYER_PERMISSIONS),
            self::BUYER_B => $this->membership(self::BUYER_B, self::FIRST_COMPANY, CompanyRoleCode::BUYER, self::BUYER_PERMISSIONS),
            self::APPROVER => $this->membership(self::APPROVER, self::FIRST_COMPANY, CompanyRoleCode::APPROVER, self::APPROVER_PERMISSIONS),
            self::ADMINISTRATOR => $this->membership(self::ADMINISTRATOR, self::FIRST_COMPANY, CompanyRoleCode::ADMINISTRATOR, self::ADMINISTRATOR_PERMISSIONS),
            self::OTHER_ADMINISTRATOR => $this->membership(self::OTHER_ADMINISTRATOR, self::SECOND_COMPANY, CompanyRoleCode::ADMINISTRATOR, self::ADMINISTRATOR_PERMISSIONS),
        ]);
    }

    /**
     * @param list<string> $permissions
     */
    private function membership(int $customerId, int $companyId, string $roleCode, array $permissions, bool $companyActive = true): CompanyMembership
    {
        return new CompanyMembership(
            companyId: $companyId,
            customerId: $customerId,
            roleCode: $roleCode,
            permissions: $permissions,
            companyActive: $companyActive,
            companyHasBillingAddress: true,
            orderLimit: null,
            periodLimit: null,
            period: null,
        );
    }

    private function order(?int $placedBy, ?int $company, ?int $owner = null): Order
    {
        $order = new Order();
        $order->setCustomerId($owner ?? $placedBy);
        $order->setPlacedByCustomerId($placedBy);
        $order->setCompanyId($company);

        return $order;
    }

    private function address(int $creator, ?int $company): Address
    {
        $address = new Address();
        $address->setCustomerId($creator);
        $address->setCompanyId($company);

        return $address;
    }
}
