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
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;

/**
 * The company, its buyers and its roles as a buyer reads and writes them: their own company, within
 * what their role allows, and within what their own role holds when they hand something to another.
 */
final class CompanyFrontApiTest extends CompanyApiTestCase
{
    private Company $first;

    private Company $second;

    private Customer $administrator;

    private Customer $buyer;

    private Customer $administratorOfSecond;

    protected function setUp(): void
    {
        parent::setUp();

        $this->first = $this->company('Acme');
        $this->first->setSiret('11111111111111')->setInternalNote('slow payer')->setNotificationEmail('orders@acme.test')->save($this->getPropelConnection());
        $this->second = $this->company('Globex');
        $this->administrator = $this->buyerOf($this->first, CompanyRoleCode::ADMINISTRATOR);
        $this->buyer = $this->buyerOf($this->first, CompanyRoleCode::BUYER, '500', '2000', 'month');
        $this->administratorOfSecond = $this->buyerOf($this->second, CompanyRoleCode::ADMINISTRATOR);
    }

    public function testABuyerReadsTheirCompanyWithoutTheNoteForTheAdministrators(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/account/company', token: $this->token($this->buyer));

        self::assertJsonResponseSuccessful($response);
        $body = $this->body($response);
        self::assertSame('Acme', $body['name']);
        self::assertSame('orders@acme.test', $body['notificationEmail']);
        self::assertTrue($body['active']);
        self::assertArrayNotHasKey('internalNote', $body);
        self::assertArrayNotHasKey('parentId', $body);
        self::assertArrayNotHasKey('billingAddressId', $body);
    }

    public function testAnAccountWithoutCompanyAndAnAnonymousCallerReadNothing(): void
    {
        self::assertSame(403, $this->jsonRequest('GET', '/api/front/account/company', token: $this->token($this->customer()))->getStatusCode());
        self::assertSame(401, $this->jsonRequest('GET', '/api/front/account/company')->getStatusCode());
    }

    public function testTheAdministratorEditsTheDetailsAndNothingMore(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/front/account/company', [
            'name' => 'Acme Corp',
            'notificationEmail' => 'compta@acme.test',
            'internalNote' => 'hacked',
            'active' => false,
            'parentId' => 99,
        ], $this->token($this->administrator), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        $stored = CompanyQuery::create()->findPk($this->first->getId());
        self::assertSame('Acme Corp', $stored?->getName());
        self::assertSame('compta@acme.test', $stored?->getNotificationEmail());
        self::assertSame('slow payer', $stored?->getInternalNote());
        self::assertSame(1, (int) $stored?->getActive());
        self::assertNull($stored?->getParentId());
    }

    public function testABuyerWithoutTheRightCannotEditTheCompany(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/front/account/company', ['name' => 'Mine'], $this->token($this->buyer), 'merge-patch+json');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Acme', CompanyQuery::create()->findPk($this->first->getId())?->getName());
    }

    public function testTheIdentifiersStayWhenAnOrderOfTheCompanyWasLeftWithoutVat(): void
    {
        $order = $this->orderOf($this->buyer, $this->first, $this->buyer);
        $order->getOrderAddressRelatedByInvoiceOrderAddressId()?->setVatExempted(1)->save($this->getPropelConnection());
        $token = $this->token($this->administrator);

        self::assertSame(409, $this->jsonRequest('PATCH', '/api/front/account/company', ['siret' => '22222222222222'], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame('11111111111111', CompanyQuery::create()->findPk($this->first->getId())?->getSiret());
        self::assertSame(200, $this->jsonRequest('PATCH', '/api/front/account/company', ['name' => 'Acme Corp'], $token, 'merge-patch+json')->getStatusCode(), 'The name is not an identifier.');
    }

    public function testTheIdentifiersChangeWhenNoOrderWasLeftWithoutVat(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/front/account/company', ['siret' => '22222222222222'], $this->token($this->administrator), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertSame('22222222222222', CompanyQuery::create()->findPk($this->first->getId())?->getSiret());
    }

    public function testIdentifiersTheRulesOfAnAddressRefuseAreUnprocessableAndTheCompanyKeepsItsOwn(): void
    {
        $billing = $this->addressOf($this->administrator, $this->first, 'Billing');
        $billing->setCountryId(CountryQuery::create()->filterByIsoalpha2('FR')->findOne()?->getId())->save($this->getPropelConnection());
        $this->first->setBillingAddressId((int) $billing->getId())->save($this->getPropelConnection());
        $token = $this->token($this->administrator);

        $refused = $this->jsonRequest('PATCH', '/api/front/account/company', ['siret' => '12345678900011'], $token, 'merge-patch+json');

        self::assertSame(422, $refused->getStatusCode(), (string) $refused->getContent());
        self::assertSame('11111111111111', CompanyQuery::create()->findPk($this->first->getId())?->getSiret());

        $accepted = $this->jsonRequest('PATCH', '/api/front/account/company', ['siret' => '30326504500003', 'vatNumber' => 'FR40303265045'], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($accepted);
        self::assertSame('30326504500003', CompanyQuery::create()->findPk($this->first->getId())?->getSiret());
    }

    public function testTheAdministratorListsTheBuyersOfTheirCompanyOnly(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/account/company/customers', token: $this->token($this->administrator));

        self::assertJsonResponseSuccessful($response);
        $members = $this->body($response)['hydra:member'];
        self::assertEqualsCanonicalizing([$this->administrator->getEmail(), $this->buyer->getEmail()], array_column($members, 'email'));
        self::assertNotContains($this->administratorOfSecond->getEmail(), array_column($members, 'email'));

        $row = array_values(array_filter($members, fn (array $member): bool => $member['email'] === $this->buyer->getEmail()))[0];
        self::assertSame('buyer', $row['roleCode']);
        self::assertSame('month', $row['period']);
        self::assertArrayNotHasKey('companyId', $row);
    }

    public function testABuyerWithoutTheRightReadsNoBuyerAndNoRole(): void
    {
        $token = $this->token($this->buyer);
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($this->administrator->getId());

        self::assertSame(403, $this->jsonRequest('GET', '/api/front/account/company/customers', token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('GET', '/api/front/account/company/customers/'.$link->getId(), token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('GET', '/api/front/account/company/roles', token: $token)->getStatusCode());
    }

    public function testTheBuyerOfAnotherCompanyReadsAsAbsent(): void
    {
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($this->buyer->getId());
        $token = $this->token($this->administratorOfSecond);

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/company/customers/'.$link->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$link->getId(), ['roleCode' => 'approver'], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(404, $this->jsonRequest('DELETE', '/api/front/account/company/customers/'.$link->getId(), token: $token)->getStatusCode());
        self::assertSame('buyer', CompanyCustomerQuery::create()->findPk($link->getId())?->getCompanyRole()?->getCode());
    }

    public function testTheAdministratorChangesTheRoleAndTheLimitsOfABuyer(): void
    {
        $this->roleOf('purchaser', $this->first, ['order.place', 'order.view_own', 'order.view_company']);
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($this->buyer->getId());

        $response = $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$link->getId(), ['roleCode' => 'purchaser', 'orderLimit' => '250', 'periodLimit' => null, 'period' => null], $this->token($this->administrator), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        $stored = CompanyCustomerQuery::create()->findPk($link->getId());
        self::assertSame('purchaser', $stored?->getCompanyRole()?->getCode());
        self::assertSame(0, bccomp('250', (string) $stored?->getOrderLimit(), 6));
        self::assertNull($stored?->getPeriodLimit());
    }

    public function testASimpleBuyerCannotChangeAColleague(): void
    {
        $colleague = $this->buyerOf($this->first);
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($colleague->getId());

        $response = $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$link->getId(), ['roleCode' => 'approver'], $this->token($this->buyer), 'merge-patch+json');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('buyer', CompanyCustomerQuery::create()->findPk($link->getId())?->getCompanyRole()?->getCode());
    }

    public function testAManagerCannotGiveARoleAboveTheirOwn(): void
    {
        $this->roleOf('manager', $this->first, ['order.place', 'order.view_own', 'buyer.manage']);
        $manager = $this->buyerOf($this->first, 'manager');
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($this->buyer->getId());
        $token = $this->token($manager);

        self::assertSame(403, $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$link->getId(), ['roleCode' => CompanyRoleCode::ADMINISTRATOR], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$link->getId(), ['roleCode' => 'approver'], $token, 'merge-patch+json')->getStatusCode(), 'approver holds order.view_company, which the manager lacks.');
        self::assertSame('buyer', CompanyCustomerQuery::create()->findPk($link->getId())?->getCompanyRole()?->getCode());
        self::assertSame(200, $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$link->getId(), ['roleCode' => 'buyer', 'orderLimit' => '100'], $token, 'merge-patch+json')->getStatusCode());
    }

    public function testAManagerCannotRaiseTheLimitsAboveTheirOwn(): void
    {
        $this->roleOf('manager', $this->first, ['order.place', 'order.view_own', 'buyer.manage']);
        $manager = $this->buyerOf($this->first, 'manager', '300', '1000', 'month');
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($this->buyer->getId());
        $token = $this->token($manager);

        self::assertSame(403, $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$link->getId(), ['orderLimit' => '301'], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$link->getId(), ['orderLimit' => null], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(200, $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$link->getId(), ['orderLimit' => '300', 'periodLimit' => '1000'], $token, 'merge-patch+json')->getStatusCode());
    }

    public function testAManagerCannotChangeOrDetachAnAdministrator(): void
    {
        $this->roleOf('manager', $this->first, ['order.place', 'order.view_own', 'buyer.manage']);
        $manager = $this->buyerOf($this->first, 'manager');
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($this->administrator->getId());
        $token = $this->token($manager);

        self::assertSame(403, $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$link->getId(), ['orderLimit' => '1'], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('DELETE', '/api/front/account/company/customers/'.$link->getId(), token: $token)->getStatusCode());
        self::assertNotNull(CompanyCustomerQuery::create()->findPk($link->getId()));
    }

    public function testAnAdministratorCannotChangeTheRoleOfAColleagueWhoseRoleGoesBeyondTheirOwn(): void
    {
        $approver = $this->buyerOf($this->first, 'approver');
        $approverLink = CompanyCustomerQuery::create()->findOneByCustomerId($approver->getId());
        $token = $this->token($this->administrator);

        // order.approve is not one of the permissions of an administrator: the approver cannot be detached, nor demoted to get there.
        self::assertSame(403, $this->jsonRequest('DELETE', '/api/front/account/company/customers/'.$approverLink->getId(), token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$approverLink->getId(), ['roleCode' => 'buyer'], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$approverLink->getId(), ['orderLimit' => '10'], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame('approver', CompanyCustomerQuery::create()->findPk($approverLink->getId())?->getCompanyRole()?->getCode());
        self::assertNotNull(CompanyCustomerQuery::create()->findPk($approverLink->getId()));
    }

    public function testAManagerCannotDemoteAnAdministratorToARoleTheyMayGive(): void
    {
        $this->roleOf('manager', $this->first, ['order.place', 'order.view_own', 'buyer.manage']);
        $manager = $this->buyerOf($this->first, 'manager', '300', '1000', 'month');
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($this->administrator->getId());

        self::assertSame(403, $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$link->getId(), ['roleCode' => 'buyer', 'orderLimit' => '300', 'periodLimit' => '1000', 'period' => 'month'], $this->token($manager), 'merge-patch+json')->getStatusCode());
        self::assertSame(CompanyRoleCode::ADMINISTRATOR, CompanyCustomerQuery::create()->findPk($link->getId())?->getCompanyRole()?->getCode());
    }

    public function testAnAdministratorChangesTheRoleOfAColleagueWhoseRoleTheyHold(): void
    {
        $colleague = $this->buyerOf($this->first, CompanyRoleCode::ADMINISTRATOR);
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($colleague->getId());

        self::assertSame(200, $this->jsonRequest('PATCH', '/api/front/account/company/customers/'.$link->getId(), ['roleCode' => 'buyer'], $this->token($this->administrator), 'merge-patch+json')->getStatusCode());
        self::assertSame('buyer', CompanyCustomerQuery::create()->findPk($link->getId())?->getCompanyRole()?->getCode());
    }

    public function testTheAdministratorDetachesABuyerAndTheLastAdministratorStays(): void
    {
        $token = $this->token($this->administrator);
        $buyerLink = CompanyCustomerQuery::create()->findOneByCustomerId($this->buyer->getId());
        $ownLink = CompanyCustomerQuery::create()->findOneByCustomerId($this->administrator->getId());

        self::assertSame(204, $this->jsonRequest('DELETE', '/api/front/account/company/customers/'.$buyerLink->getId(), token: $token)->getStatusCode());
        self::assertNull(CompanyCustomerQuery::create()->findPk($buyerLink->getId()));

        self::assertSame(409, $this->jsonRequest('DELETE', '/api/front/account/company/customers/'.$ownLink->getId(), token: $token)->getStatusCode());
        self::assertNotNull(CompanyCustomerQuery::create()->findPk($ownLink->getId()));
    }

    public function testTheRolesAManagerMayPickAreTheSharedOnesAndThoseOfTheirCompany(): void
    {
        $own = $this->roleOf('purchaser', $this->first, ['order.place']);
        $foreign = $this->roleOf('foreign', $this->second, ['order.place']);

        $response = $this->jsonRequest('GET', '/api/front/account/company/roles', token: $this->token($this->administrator));

        self::assertJsonResponseSuccessful($response);
        $codes = array_column($this->body($response)['hydra:member'], 'code');
        self::assertContains('purchaser', $codes);
        self::assertContains('buyer', $codes);
        self::assertContains('company_admin', $codes);
        self::assertNotContains('foreign', $codes);
        self::assertNotNull($own);
        self::assertNotNull($foreign);
    }

    public function testTheCustomerResourceOfABuyerCarriesNoCompanyLink(): void
    {
        $token = $this->token($this->buyer);
        $read = $this->jsonRequest('GET', '/api/front/account/customers/'.$this->buyer->getId(), token: $token);

        self::assertJsonResponseSuccessful($read);
        $body = $this->body($read);

        foreach (['company', 'companyId', 'companyRole', 'roleCode', 'orderLimit', 'periodLimit', 'period', 'role', 'roles'] as $key) {
            self::assertArrayNotHasKey($key, $body);
        }

        $this->jsonRequest('PUT', '/api/front/account/customers/'.$this->buyer->getId(), ['company' => $this->second->getId(), 'roleCode' => CompanyRoleCode::ADMINISTRATOR, 'orderLimit' => null], $token);
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($this->buyer->getId());
        self::assertSame($this->first->getId(), $link?->getCompanyId());
        self::assertSame('buyer', $link?->getCompanyRole()?->getCode());
        self::assertNotNull($link?->getOrderLimit());
    }
}
