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

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\AddressQuery;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyQuery;
use Thelia\Model\CompanyRoleQuery;
use Thelia\Model\CountryQuery;

/**
 * The companies, their buyers and their roles as the administration reads and writes them.
 */
final class CompanyAdminApiTest extends CompanyApiTestCase
{
    public function testACompanyIsCreatedReadListedAndFiltered(): void
    {
        $token = $this->authenticateAsAdmin();

        $created = $this->jsonRequest('POST', '/api/admin/companies', ['name' => 'Acme', 'siret' => '12345678901234', 'notificationEmail' => 'orders@acme.test', 'internalNote' => 'slow payer'], $token);

        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
        $body = $this->body($created);
        self::assertSame('Acme', $body['name']);
        self::assertTrue($body['active']);
        self::assertSame('slow payer', $body['internalNote']);

        $this->jsonRequest('POST', '/api/admin/companies', ['name' => 'Globex'], $token);

        $read = $this->jsonRequest('GET', '/api/admin/companies/'.$body['id'], token: $token);
        self::assertJsonResponseSuccessful($read);
        self::assertSame('12345678901234', $this->body($read)['siret']);

        $filtered = $this->jsonRequest('GET', '/api/admin/companies?name=Glob', token: $token);
        self::assertHydraTotalItems(1, $filtered);
        self::assertSame('Globex', $this->body($filtered)['hydra:member'][0]['name']);
    }

    public function testACompanyWithoutNameIsRefused(): void
    {
        $response = $this->jsonRequest('POST', '/api/admin/companies', ['siret' => '123'], $this->authenticateAsAdmin());

        self::assertSame(422, $response->getStatusCode());
    }

    public function testIdentifiersTheRulesOfAnAddressRefuseAreAnUnprocessableEntityAndTheCompanyKeepsItsOwn(): void
    {
        $token = $this->authenticateAsAdmin();
        $company = $this->company('Acme');
        $billing = $this->addressOf($this->customer(), $company, 'Billing');
        $billing->setCountryId(CountryQuery::create()->filterByIsoalpha2('FR')->findOne()?->getId())->save($this->getPropelConnection());
        $company->setSiret('30326504500003')->setBillingAddressId((int) $billing->getId())->save($this->getPropelConnection());

        $refused = $this->jsonRequest('PATCH', '/api/admin/companies/'.$company->getId(), ['siret' => '12345678900011'], $token, 'merge-patch+json');

        self::assertSame(422, $refused->getStatusCode(), (string) $refused->getContent());
        self::assertStringContainsString('checksum of this SIRET', (string) $refused->getContent());
        self::assertSame('30326504500003', CompanyQuery::create()->findPk($company->getId())?->getSiret());

        $accepted = $this->jsonRequest('PATCH', '/api/admin/companies/'.$company->getId(), ['siret' => '30326504500003', 'vatNumber' => 'FR40303265045'], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($accepted);
        self::assertSame('FR40303265045', AddressQuery::create()->findPk($billing->getId())?->getVatNumber());
    }

    public function testACompanyCreatedWithAnIdentifierNoCountryAcceptsIsUnprocessable(): void
    {
        $response = $this->jsonRequest('POST', '/api/admin/companies', ['name' => 'Acme', 'siret' => 'AB#12'], $this->authenticateAsAdmin());

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, CompanyQuery::create()->filterByName('Acme')->count());
    }

    public function testAPatchChangesTheDetailsAndSuspendsAndReactivatesTheCompany(): void
    {
        $token = $this->authenticateAsAdmin();
        $company = $this->company('Acme');
        $company->setInternalNote('keep')->save($this->getPropelConnection());
        $url = '/api/admin/companies/'.$company->getId();

        $renamed = $this->jsonRequest('PATCH', $url, ['name' => 'Acme Corp', 'active' => false], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($renamed);
        $stored = CompanyQuery::create()->findPk($company->getId());
        self::assertSame('Acme Corp', $stored?->getName());
        self::assertSame(0, (int) $stored?->getActive());
        self::assertSame('keep', $stored?->getInternalNote(), 'What a patch leaves out keeps its value.');

        $this->jsonRequest('PATCH', $url, ['active' => true], $token, 'merge-patch+json');

        self::assertSame(1, (int) CompanyQuery::create()->findPk($company->getId())?->getActive());
    }

    public function testACompanyWithAnOrderIsNotDeletedAndOneWithoutIsAndItsBuyersAreReleased(): void
    {
        $token = $this->authenticateAsAdmin();
        $withOrder = $this->company('History');
        $buyer = $this->buyerOf($withOrder);
        $this->orderOf($buyer, $withOrder, $buyer);
        $empty = $this->company('Empty');
        $this->buyerOf($empty);

        self::assertSame(409, $this->jsonRequest('DELETE', '/api/admin/companies/'.$withOrder->getId(), token: $token)->getStatusCode());
        self::assertNotNull(CompanyQuery::create()->findPk($withOrder->getId()));

        self::assertSame(204, $this->jsonRequest('DELETE', '/api/admin/companies/'.$empty->getId(), token: $token)->getStatusCode());
        self::assertNull(CompanyQuery::create()->findPk($empty->getId()));
        self::assertSame(0, CompanyCustomerQuery::create()->filterByCompanyId($empty->getId())->count());
    }

    public function testAnAccountIsAttachedByItsIdThenRefusedASecondTime(): void
    {
        $token = $this->authenticateAsAdmin();
        $company = $this->company();
        $customer = $this->customer();

        $attached = $this->jsonRequest('POST', '/api/admin/company_customers', [
            'companyId' => $company->getId(),
            'customerId' => $customer->getId(),
            'roleCode' => CompanyRoleCode::BUYER,
            'orderLimit' => '500',
            'periodLimit' => '2000',
            'period' => 'month',
        ], $token);

        self::assertSame(201, $attached->getStatusCode(), (string) $attached->getContent());
        $body = $this->body($attached);
        self::assertSame('buyer', $body['roleCode']);
        self::assertSame($customer->getEmail(), $body['email']);
        self::assertSame('month', $body['period']);
        self::assertSame($company->getId(), CompanyCustomerQuery::create()->findOneByCustomerId($customer->getId())?->getCompanyId());

        $again = $this->jsonRequest('POST', '/api/admin/company_customers', ['companyId' => $company->getId(), 'customerId' => $customer->getId(), 'roleCode' => 'buyer'], $token);
        self::assertSame(409, $again->getStatusCode());
    }

    public function testAnAccountIsAttachedByItsEmailAddressAndAnUnknownOneIsAbsent(): void
    {
        $token = $this->authenticateAsAdmin();
        $company = $this->company();
        $customer = $this->customer('buyer@acme.test');

        self::assertSame(201, $this->jsonRequest('POST', '/api/admin/company_customers', ['companyId' => $company->getId(), 'customerEmail' => 'buyer@acme.test', 'roleCode' => 'buyer'], $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('POST', '/api/admin/company_customers', ['companyId' => $company->getId(), 'customerEmail' => 'nobody@acme.test', 'roleCode' => 'buyer'], $token)->getStatusCode());
        self::assertNotNull(CompanyCustomerQuery::create()->findOneByCustomerId($customer->getId()));
    }

    public function testTheLookupByEmailAddressIsRateLimited(): void
    {
        $token = $this->authenticateAsAdmin();
        $company = $this->company();
        $statuses = [];

        for ($attempt = 0; $attempt < 32; ++$attempt) {
            $statuses[] = $this->jsonRequest('POST', '/api/admin/company_customers', ['companyId' => $company->getId(), 'customerEmail' => 'nobody'.$attempt.'@acme.test', 'roleCode' => 'buyer'], $token)->getStatusCode();
        }

        self::assertSame(404, $statuses[0]);
        self::assertSame(429, end($statuses));
    }

    public function testAGuestOrAnUnknownRoleCannotBeAttached(): void
    {
        $token = $this->authenticateAsAdmin();
        $company = $this->company();
        $guest = $this->fixtures->guestCustomer($this->fixtures->customerTitle());

        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/company_customers', ['companyId' => $company->getId(), 'customerId' => $guest->getId(), 'roleCode' => 'buyer'], $token)->getStatusCode());
        self::assertSame(422, $this->jsonRequest('POST', '/api/admin/company_customers', ['companyId' => $company->getId(), 'customerId' => $this->customer()->getId(), 'roleCode' => 'nope'], $token)->getStatusCode());
    }

    public function testTheRoleAndTheLimitsOfABuyerChangeAndTheLastAdministratorKeepsTheRole(): void
    {
        $token = $this->authenticateAsAdmin();
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyerOf($company);
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($buyer->getId());

        $changed = $this->jsonRequest('PATCH', '/api/admin/company_customers/'.$link->getId(), ['roleCode' => 'approver', 'orderLimit' => '100', 'periodLimit' => '300', 'period' => 'week'], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($changed);
        $stored = CompanyCustomerQuery::create()->findPk($link->getId());
        self::assertSame('approver', $stored?->getCompanyRole()?->getCode());
        self::assertSame(0, bccomp('100', (string) $stored?->getOrderLimit(), 6));
        self::assertSame('week', $stored?->getPeriod());

        $last = CompanyCustomerQuery::create()->findOneByCustomerId($administrator->getId());
        self::assertSame(409, $this->jsonRequest('PATCH', '/api/admin/company_customers/'.$last->getId(), ['roleCode' => 'buyer'], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(409, $this->jsonRequest('DELETE', '/api/admin/company_customers/'.$last->getId(), token: $token)->getStatusCode());
        self::assertSame(204, $this->jsonRequest('DELETE', '/api/admin/company_customers/'.$link->getId(), token: $token)->getStatusCode());
    }

    public function testALimitsEventIsHeardOnlyWhenTheLimitsChange(): void
    {
        $token = $this->authenticateAsAdmin();
        $company = $this->company();
        $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyerOf($company, CompanyRoleCode::BUYER, '500', null, null);
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($buyer->getId());
        $heard = 0;
        $dispatcher = $this->getService('event_dispatcher');
        $dispatcher->addListener('action.company.customer.changeLimits', static function () use (&$heard): void {
            ++$heard;
        });

        $this->jsonRequest('PATCH', '/api/admin/company_customers/'.$link->getId(), ['roleCode' => 'approver'], $token, 'merge-patch+json');
        $this->jsonRequest('PATCH', '/api/admin/company_customers/'.$link->getId(), ['orderLimit' => '500.000000'], $token, 'merge-patch+json');
        self::assertSame(0, $heard, 'A role change, or the same limit written another way, changes no limit.');

        $this->jsonRequest('PATCH', '/api/admin/company_customers/'.$link->getId(), ['orderLimit' => '450'], $token, 'merge-patch+json');
        self::assertSame(1, $heard);
    }

    public function testTheBuyersOfACompanyAreFilteredByTheCompany(): void
    {
        $token = $this->authenticateAsAdmin();
        $first = $this->company('One');
        $second = $this->company('Two');
        $this->buyerOf($first);
        $this->buyerOf($first);
        $this->buyerOf($second);

        self::assertHydraTotalItems(2, $this->jsonRequest('GET', '/api/admin/company_customers?companyId='.$first->getId(), token: $token));
    }

    public function testARoleIsCreatedWithKnownPermissionsAndTitlesAndRefusedOtherwise(): void
    {
        $token = $this->authenticateAsAdmin();
        $company = $this->company();
        $payload = ['code' => 'purchaser', 'companyId' => $company->getId(), 'permissions' => ['order.place', 'order.view_own'], 'i18ns' => ['en_US' => ['title' => 'Purchaser']]];

        $created = $this->jsonRequest('POST', '/api/admin/company_roles', $payload, $token);

        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
        $role = CompanyRoleQuery::create()->findOneByCode('purchaser');
        self::assertSame(['order.place', 'order.view_own'], $role?->getPermissionList());
        self::assertSame('Purchaser', $role?->setLocale('en_US')->getTitle());

        self::assertSame(422, $this->jsonRequest('POST', '/api/admin/company_roles', ['code' => 'teleporter', 'permissions' => ['teleport']] + $payload, $token)->getStatusCode(), 'An unknown permission is refused.');
        self::assertNull(CompanyRoleQuery::create()->findOneByCode('teleporter'));
        self::assertSame(422, $this->jsonRequest('POST', '/api/admin/company_roles', ['code' => 'buyer'] + $payload, $token)->getStatusCode(), 'A company role does not take the code of a shared one.');
        self::assertSame(422, $this->jsonRequest('POST', '/api/admin/company_roles', $payload, $token)->getStatusCode(), 'The same code twice in a company is refused.');
    }

    public function testARoleChangesItsPermissionsButNotItsCodeAndIsDeletedOnlyWhenNobodyHoldsIt(): void
    {
        $token = $this->authenticateAsAdmin();
        $company = $this->company();
        $role = $this->roleOf('purchaser', $company, ['order.place']);
        $url = '/api/admin/company_roles/'.$role->getId();

        $patched = $this->jsonRequest('PATCH', $url, ['permissions' => ['order.place', 'order.view_company']], $token, 'merge-patch+json');
        self::assertJsonResponseSuccessful($patched);
        self::assertSame(['order.place', 'order.view_company'], CompanyRoleQuery::create()->findPk($role->getId())?->getPermissionList());

        self::assertSame(422, $this->jsonRequest('PATCH', $url, ['code' => 'other'], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(422, $this->jsonRequest('PATCH', $url, ['companyId' => $this->company('Other')->getId()], $token, 'merge-patch+json')->getStatusCode(), 'A role stays with its company.');
        self::assertSame(422, $this->jsonRequest('PATCH', $url, ['permissions' => ['teleport']], $token, 'merge-patch+json')->getStatusCode());

        $this->buyerOf($company, 'purchaser');
        self::assertSame(409, $this->jsonRequest('DELETE', $url, token: $token)->getStatusCode());

        $free = $this->roleOf('free', $company, []);
        self::assertSame(204, $this->jsonRequest('DELETE', '/api/admin/company_roles/'.$free->getId(), token: $token)->getStatusCode());
        self::assertSame(409, $this->jsonRequest('DELETE', '/api/admin/company_roles/'.$this->sharedRole('approver')->getId(), token: $token)->getStatusCode());
    }

    public function testACustomerTokenNeverOpensAnAdministrationOperation(): void
    {
        $customer = $this->customer();
        $token = $this->token($customer);
        $company = $this->company();

        foreach (['/api/admin/companies', '/api/admin/company_customers', '/api/admin/company_roles'] as $path) {
            self::assertContains($this->jsonRequest('GET', $path, token: $token)->getStatusCode(), [401, 403], $path);
        }

        self::assertContains($this->jsonRequest('POST', '/api/admin/companies', ['name' => 'Mine'], $token)->getStatusCode(), [401, 403]);
        self::assertContains($this->jsonRequest('DELETE', '/api/admin/companies/'.$company->getId(), token: $token)->getStatusCode(), [401, 403]);
        self::assertNotNull(CompanyQuery::create()->findPk($company->getId()));
    }

    public function testAnAdministratorNeedsTheCompanyRightForEveryOperation(): void
    {
        $company = $this->company();
        $withoutRight = $this->fixtures->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]);
        $readOnly = $this->fixtures->restrictedAdmin([AdminResources::COMPANY => [AccessManager::VIEW]]);

        foreach (['/api/admin/companies', '/api/admin/company_customers', '/api/admin/company_roles'] as $path) {
            self::assertSame(403, $this->jsonRequest('GET', $path, token: $this->authenticateAsAdmin($withoutRight))->getStatusCode(), $path);
            self::assertSame(200, $this->jsonRequest('GET', $path, token: $this->authenticateAsAdmin($readOnly))->getStatusCode(), $path);
        }

        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/companies', ['name' => 'X'], $this->authenticateAsAdmin($readOnly))->getStatusCode());
        self::assertSame(403, $this->jsonRequest('DELETE', '/api/admin/companies/'.$company->getId(), token: $this->authenticateAsAdmin($readOnly))->getStatusCode());
    }
}
