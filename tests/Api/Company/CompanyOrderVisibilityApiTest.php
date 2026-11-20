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

use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\Company;
use Thelia\Model\Customer;
use Thelia\Model\Order;

/**
 * Who reads which order of a company over the account API: the buyer who placed it, the buyers
 * whose role reads the orders of the company, and nobody else, whichever way the question is
 * asked: the item, the collection, the company scope.
 */
final class CompanyOrderVisibilityApiTest extends CompanyApiTestCase
{
    private Company $first;

    private Company $second;

    private Customer $buyerA;

    private Customer $buyerB;

    private Customer $approver;

    private Customer $administrator;

    private Customer $administratorOfSecond;

    private Order $orderOfA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->first = $this->company('Acme');
        $this->second = $this->company('Globex');
        $this->buyerA = $this->buyerOf($this->first);
        $this->buyerB = $this->buyerOf($this->first);
        $this->approver = $this->buyerOf($this->first, CompanyRoleCode::APPROVER);
        $this->administrator = $this->buyerOf($this->first, CompanyRoleCode::ADMINISTRATOR);
        $this->administratorOfSecond = $this->buyerOf($this->second, CompanyRoleCode::ADMINISTRATOR);
        $this->orderOfA = $this->orderOf($this->buyerA, $this->first, $this->buyerA);
    }

    public function testTheBuyerWhoPlacedAnOrderReadsIt(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$this->orderOfA->getId(), token: $this->token($this->buyerA));

        self::assertJsonResponseSuccessful($response);
        self::assertSame($this->orderOfA->getId(), $this->body($response)['id']);
    }

    public function testAnApproverAndTheAdministratorOfTheCompanyReadTheOrderOfABuyer(): void
    {
        foreach ([$this->approver, $this->administrator] as $reader) {
            $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$this->orderOfA->getId(), token: $this->token($reader));

            self::assertJsonResponseSuccessful($response);
        }
    }

    public function testAColleagueWithoutTheRightToReadTheOrdersOfTheCompanyFindsNoSuchOrder(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$this->orderOfA->getId(), token: $this->token($this->buyerB));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testTheAdministratorOfAnotherCompanyFindsNoSuchOrder(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$this->orderOfA->getId(), token: $this->token($this->administratorOfSecond));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testTheAdministratorOfTheFirstCompanyFindsNoSuchOrderOfTheSecond(): void
    {
        $orderOfSecond = $this->orderOf($this->administratorOfSecond, $this->second, $this->administratorOfSecond);

        $own = $this->jsonRequest('GET', '/api/front/account/orders/'.$orderOfSecond->getId(), token: $this->token($this->administratorOfSecond));
        $crossing = $this->jsonRequest('GET', '/api/front/account/orders/'.$orderOfSecond->getId(), token: $this->token($this->administrator));

        self::assertJsonResponseSuccessful($own);
        self::assertSame(404, $crossing->getStatusCode());
    }

    public function testTheCollectionHoldsTheOrderForWhoReadsItAndForNobodyElse(): void
    {
        $holds = fn (Customer $reader): bool => \in_array(
            (int) $this->orderOfA->getId(),
            $this->memberIds($this->jsonRequest('GET', '/api/front/account/orders', token: $this->token($reader))),
            true,
        );

        self::assertTrue($holds($this->buyerA));
        self::assertTrue($holds($this->approver));
        self::assertTrue($holds($this->administrator));
        self::assertFalse($holds($this->buyerB));
        self::assertFalse($holds($this->administratorOfSecond));
    }

    public function testTheCompanyScopeListsTheOrdersOfTheCompanyForWhoReadsThemAndNothingOtherwise(): void
    {
        $ownOrderOfTheApprover = $this->orderOf($this->approver, $this->first, $this->approver);
        $foreign = $this->orderOf($this->administratorOfSecond, $this->second, $this->administratorOfSecond);

        $ids = $this->memberIds($this->jsonRequest('GET', '/api/front/account/orders?scope=company', token: $this->token($this->approver)));

        self::assertEqualsCanonicalizing([(int) $this->orderOfA->getId(), (int) $ownOrderOfTheApprover->getId()], $ids);
        self::assertNotContains((int) $foreign->getId(), $ids);

        // A buyer whose role does not read the orders of the company is answered nothing for that scope.
        $this->orderOf($this->buyerB, $this->first, $this->buyerB);

        self::assertSame([], $this->memberIds($this->jsonRequest('GET', '/api/front/account/orders?scope=company', token: $this->token($this->buyerB))));
    }

    public function testTheOwnScopeKeepsTheOrdersThePlacerOfTheCompanyOrdersMade(): void
    {
        $ownOrder = $this->orderOf($this->approver, $this->first, $this->approver);

        self::assertSame(
            [(int) $ownOrder->getId()],
            $this->memberIds($this->jsonRequest('GET', '/api/front/account/orders?scope=own', token: $this->token($this->approver))),
        );
    }

    public function testTheCompanyScopeIsEmptyForAnAccountWithoutCompany(): void
    {
        $loner = $this->customer();
        $this->orderOf($loner);

        self::assertSame([], $this->memberIds($this->jsonRequest('GET', '/api/front/account/orders?scope=company', token: $this->token($loner))));
        self::assertCount(1, $this->memberIds($this->jsonRequest('GET', '/api/front/account/orders', token: $this->token($loner))));
    }

    public function testAnOrderPlacedForACompanyStaysWithItsBuyerAfterTheyLeaveIt(): void
    {
        $this->facade->detach($this->buyerA);
        $this->forgetMemberships();

        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/front/account/orders/'.$this->orderOfA->getId(), token: $this->token($this->buyerA)));
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/orders/'.$this->orderOfA->getId(), token: $this->token($this->buyerB))->getStatusCode());
    }

    public function testAnOrderOfAnAccountWithoutCompanyIsStillReadByItsOwnerOnly(): void
    {
        $loner = $this->customer();
        $other = $this->customer();
        $order = $this->orderOf($loner);

        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->token($loner)));
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->token($other))->getStatusCode());
    }

    public function testAnOrderPlacedBeforeJoiningTheCompanyStaysWithItsCustomer(): void
    {
        $before = $this->orderOf($this->buyerB);

        self::assertContains((int) $before->getId(), $this->memberIds($this->jsonRequest('GET', '/api/front/account/orders', token: $this->token($this->buyerB))));
        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/front/account/orders/'.$before->getId(), token: $this->token($this->buyerB)));
        self::assertNotContains((int) $before->getId(), $this->memberIds($this->jsonRequest('GET', '/api/front/account/orders', token: $this->token($this->buyerA))));
    }

    public function testARoleThatReadsTheCompanyOrdersOnlyDoesNotReadTheOrdersOfItsOwnOutsideTheCompany(): void
    {
        $this->roleOf('auditor', $this->first, ['order.view_company']);
        $auditor = $this->buyerOf($this->first, 'auditor');
        $personal = $this->orderOf($auditor);
        $token = $this->token($auditor);

        $ids = $this->memberIds($this->jsonRequest('GET', '/api/front/account/orders', token: $token));

        self::assertContains((int) $this->orderOfA->getId(), $ids);
        self::assertNotContains((int) $personal->getId(), $ids);
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/orders/'.$personal->getId(), token: $token)->getStatusCode());
    }
}
