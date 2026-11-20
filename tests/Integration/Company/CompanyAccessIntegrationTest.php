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

use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\AddressQuery;
use Thelia\Model\OrderQuery;

/**
 * The checker over real rows: two companies, their buyers, their orders and their addresses, and
 * what the facade changes is what the checker judges next.
 */
final class CompanyAccessIntegrationTest extends CompanyDomainTestCase
{
    public function testTheOrdersOfACompanyAreSeenByTheRightPeopleInBothDirections(): void
    {
        $first = $this->company('Acme');
        $second = $this->company('Globex');
        $buyerA = $this->buyerOf($first);
        $buyerB = $this->buyerOf($first);
        $approver = $this->buyerOf($first, CompanyRoleCode::APPROVER);
        $administratorOfFirst = $this->buyerOf($first, CompanyRoleCode::ADMINISTRATOR);
        $administratorOfSecond = $this->buyerOf($second, CompanyRoleCode::ADMINISTRATOR);
        $buyerOfSecond = $this->buyerOf($second);
        $stranger = $this->customer();

        $orderOfFirst = OrderQuery::create()->findPk($this->orderOf($buyerA, $first, $buyerA)->getId());
        $orderOfSecond = OrderQuery::create()->findPk($this->orderOf($buyerOfSecond, $second, $buyerOfSecond)->getId());

        foreach ([$buyerA, $approver, $administratorOfFirst] as $viewer) {
            self::assertTrue($this->checker->canAccessOrder($viewer, $orderOfFirst), 'Seen by '.$viewer->getId());
            self::assertFalse($this->checker->canAccessOrder($viewer, $orderOfSecond), 'Not seen by '.$viewer->getId());
        }

        foreach ([$buyerB, $administratorOfSecond, $stranger] as $viewer) {
            self::assertFalse($this->checker->canAccessOrder($viewer, $orderOfFirst), 'Not seen by '.$viewer->getId());
        }

        self::assertTrue($this->checker->canAccessOrder($administratorOfSecond, $orderOfSecond));
        self::assertTrue($this->checker->canAccessOrder($buyerOfSecond, $orderOfSecond));
        self::assertFalse($this->checker->canAccessOrder($buyerA, $orderOfSecond));
        self::assertFalse($this->checker->canAccessOrder($administratorOfFirst, $orderOfSecond));
    }

    public function testABuyerWhoLeavesKeepsHisOrdersAndTheCompanyKeepsItsHistory(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyerOf($company);
        $order = OrderQuery::create()->findPk($this->orderOf($buyer, $company, $buyer)->getId());

        self::assertTrue($this->checker->canAccessOrder($administrator, $order));

        $this->facade->detach($buyer);

        self::assertTrue($this->checker->canAccessOrder($buyer, $order), 'The account that placed an order reads it after leaving.');
        self::assertTrue($this->checker->canAccessOrder($administrator, $order), 'The company keeps its history.');
        self::assertSame($company->getId(), $order->getCompanyId());
    }

    public function testTheSharedAddressesFollowTheCreatorWhoLeavesAndThePersonalOnesDoNot(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $colleague = $this->buyerOf($company);
        $creator = $this->buyerOf($company);

        $warehouse = $this->addressOf($creator, $company, 'Warehouse');
        $home = $this->addressOf($creator, null, 'Home');

        self::assertTrue($this->checker->canUseAddress($colleague, $warehouse));
        self::assertFalse($this->checker->canEditAddress($colleague, $warehouse));
        self::assertFalse($this->checker->canUseAddress($colleague, $home));
        self::assertTrue($this->checker->canEditAddress($administrator, $warehouse));

        $this->facade->detach($creator);

        $warehouse = AddressQuery::create()->findPk($warehouse->getId());
        $home = AddressQuery::create()->findPk($home->getId());

        self::assertTrue($this->checker->canEditAddress($administrator, $warehouse), 'It belongs to the administrator now.');
        self::assertTrue($this->checker->canUseAddress($colleague, $warehouse));
        self::assertFalse($this->checker->canUseAddress($creator, $warehouse), 'The creator has left the company that shares it.');
        self::assertTrue($this->checker->canEditAddress($creator, $home));
        self::assertFalse($this->checker->canUseAddress($administrator, $home));
    }

    public function testASuspendedCompanyStopsPlacingAndKeepsItsHistory(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyerOf($company);
        $order = OrderQuery::create()->findPk($this->orderOf($buyer, $company, $buyer)->getId());

        $this->facade->suspend($company);

        self::assertFalse($this->checker->can($buyer, CompanyPermission::ORDER_PLACE));
        self::assertFalse($this->checker->can($administrator, CompanyPermission::ORDER_PLACE));
        self::assertTrue($this->checker->canAccessOrder($buyer, $order));
        self::assertTrue($this->checker->canAccessOrder($administrator, $order));

        $this->facade->reactivate($company);

        self::assertTrue($this->checker->can($buyer, CompanyPermission::ORDER_PLACE));
    }

    public function testVisibleCompanyFollowsTheRoleGivenToABuyer(): void
    {
        $company = $this->company();
        $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyerOf($company);

        self::assertNull($this->checker->visibleCompanyIdFor($buyer, CompanyPermission::ORDER_VIEW_COMPANY));

        $this->facade->changeRole($buyer, CompanyRoleCode::APPROVER);

        self::assertSame($company->getId(), $this->checker->visibleCompanyIdFor($buyer, CompanyPermission::ORDER_VIEW_COMPANY));
    }
}
