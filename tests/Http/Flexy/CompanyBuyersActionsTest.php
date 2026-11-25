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

namespace Thelia\Tests\Http\Flexy;

use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Company\Service\CompanyInvitationService;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\ConfigQuery;

/**
 * What a buyer who manages the buyers does on the buyers page: change a role and the limits,
 * remove a buyer, invite a person. Each is a POST with a token, and what the API refuses (a role
 * beyond the issuer's, the last administrator) comes back as a sentence of the page.
 */
final class CompanyBuyersActionsTest extends CompanyAccountTestCase
{
    public function testAnAdministratorChangesTheRoleAndTheLimitsOfABuyer(): void
    {
        $company = $this->company();
        $this->roleOf('viewer', [CompanyPermission::ORDER_VIEW_OWN]);
        $admin = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyer($company);
        $this->signInAs($admin);
        $page = $this->get('/account/company/buyers');
        $row = (int) CompanyCustomerQuery::create()->filterByCustomerId($buyer->getId())->findOne()?->getId();

        $this->client->request('POST', $this->url('account_company_buyer_update', ['buyerId' => $row]), [
            '_token' => $this->tokenOf($page),
            'role' => 'viewer',
            'order_limit' => '500',
            'period_limit' => '2000',
            'period' => 'month',
        ]);

        self::assertResponseRedirects('/account/company/buyers?success=buyer_updated');
        $stored = CompanyCustomerQuery::create()->findPk($row);
        self::assertSame('viewer', $stored?->getCompanyRole()?->getCode());
        self::assertSame(0, bccomp('500', (string) $stored->getOrderLimit()));
        self::assertSame('month', $stored->getPeriod());
    }

    public function testTheLimitsOfABuyerAreShownAsAmountsAndTheFieldsHoldThemWithoutPaddingZeros(): void
    {
        $company = $this->company();
        $admin = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyer($company);
        $this->companies->changeLimits($buyer, '500.000000', '2000.500000', 'month');
        $this->signInAs($admin);

        $page = $this->get('/account/company/buyers');

        $summary = $page->filter('[data-testid="company-buyer-limits"]');
        self::assertCount(1, $summary, 'Only the buyer who has limits has a line for them.');
        self::assertStringContainsString('€500.00', $summary->text());
        self::assertStringContainsString('€2,000.50', $summary->text());
        self::assertStringContainsString('Month', $summary->text());
        $row = (int) CompanyCustomerQuery::create()->filterByCustomerId($buyer->getId())->findOne()?->getId();
        self::assertSame('500', $page->filter('#order-limit-'.$row)->attr('value'));
        self::assertSame('2000.50', $page->filter('#period-limit-'.$row)->attr('value'));
        self::assertStringNotContainsString('000000', $page->html());
    }

    public function testTheRolesOfferedAreTheOnesTheManagerHoldsThemselves(): void
    {
        $company = $this->company();
        $this->roleOf('manager', [CompanyPermission::ORDER_PLACE, CompanyPermission::ORDER_VIEW_OWN, CompanyPermission::QUOTE_REQUEST, CompanyPermission::BUYER_MANAGE]);
        $manager = $this->buyer($company, 'manager');
        $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $this->signInAs($manager);

        $page = $this->get('/account/company/buyers');

        self::assertSame(200, $this->statusCode());
        $offered = $page->filter('#invitation-role option')->each(static fn ($option): string => (string) $option->attr('value'));
        self::assertContains(CompanyRoleCode::BUYER, $offered);
        self::assertSame(['Buyer', 'Manager'], $page->filter('#invitation-role option')->each(static fn ($option): string => $option->text()), 'The roles are named by their title, not their code.');
        self::assertNotContains(CompanyRoleCode::ADMINISTRATOR, $offered, 'A role with a permission the manager lacks is not offered.');
    }

    public function testABuyerWhoHoldsMoreThanTheManagerIsShownButNotEditable(): void
    {
        $company = $this->company();
        $this->roleOf('manager', [CompanyPermission::ORDER_PLACE, CompanyPermission::ORDER_VIEW_OWN, CompanyPermission::QUOTE_REQUEST, CompanyPermission::BUYER_MANAGE]);
        $manager = $this->buyer($company, 'manager');
        $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $this->signInAs($manager);

        $page = $this->get('/account/company/buyers');

        self::assertCount(1, $page->filter('[data-testid="company-buyer-role"]'), 'The administrator is read-only to the manager.');
        self::assertCount(1, $page->filter('[data-testid="company-buyer"] form'), 'Only the manager row has a form.');
    }

    public function testTheLastAdministratorCannotLeave(): void
    {
        $company = $this->company();
        $admin = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $this->buyer($company);
        $this->signInAs($admin);
        $page = $this->get('/account/company/buyers');
        $row = (int) CompanyCustomerQuery::create()->filterByCustomerId($admin->getId())->findOne()?->getId();

        $this->client->request('POST', $this->url('account_company_buyer_detach', ['buyerId' => $row]), ['_token' => $this->tokenOf($page)]);

        self::assertResponseRedirects('/account/company/buyers?error=last_administrator');
        self::assertNotNull(CompanyCustomerQuery::create()->filterByCustomerId($admin->getId())->findOne());
    }

    public function testAnAdministratorRemovesABuyer(): void
    {
        $company = $this->company();
        $admin = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyer($company);
        $this->signInAs($admin);
        $page = $this->get('/account/company/buyers');
        $row = (int) CompanyCustomerQuery::create()->filterByCustomerId($buyer->getId())->findOne()?->getId();

        $this->client->request('POST', $this->url('account_company_buyer_detach', ['buyerId' => $row]), ['_token' => $this->tokenOf($page)]);

        self::assertResponseRedirects('/account/company/buyers?success=buyer_detached');
        self::assertNull(CompanyCustomerQuery::create()->filterByCustomerId($buyer->getId())->findOne());
    }

    public function testASimpleBuyerCannotRemoveAColleagueNorChangeARole(): void
    {
        $company = $this->company();
        $admin = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $colleague = $this->buyer($company);
        $this->signInAs($this->buyer($company));
        $row = (int) CompanyCustomerQuery::create()->filterByCustomerId($colleague->getId())->findOne()?->getId();

        $this->client->request('POST', $this->url('account_company_buyer_detach', ['buyerId' => $row]), ['_token' => 'any']);
        self::assertSame(403, $this->statusCode());

        $this->client->request('POST', $this->url('account_company_buyer_update', ['buyerId' => $row]), ['_token' => 'any', 'role' => CompanyRoleCode::ADMINISTRATOR]);
        self::assertSame(403, $this->statusCode());
        self::assertNotNull(CompanyCustomerQuery::create()->filterByCustomerId($admin->getId())->findOne());
        self::assertSame(CompanyRoleCode::BUYER, CompanyCustomerQuery::create()->findPk($row)?->getCompanyRole()?->getCode());
    }

    public function testABuyerOfAnotherCompanyIsNotReachedByTheForgedRow(): void
    {
        $first = $this->company('Acme');
        $second = $this->company('Globex');
        $adminOfFirst = $this->buyer($first, CompanyRoleCode::ADMINISTRATOR);
        $this->buyer($second, CompanyRoleCode::ADMINISTRATOR);
        $foreign = $this->buyer($second);
        $this->signInAs($adminOfFirst);
        $page = $this->get('/account/company/buyers');
        $row = (int) CompanyCustomerQuery::create()->filterByCustomerId($foreign->getId())->findOne()?->getId();

        $this->client->request('POST', $this->url('account_company_buyer_detach', ['buyerId' => $row]), ['_token' => $this->tokenOf($page)]);

        self::assertResponseRedirects('/account/company/buyers?error=not_found');
        self::assertNotNull(CompanyCustomerQuery::create()->filterByCustomerId($foreign->getId())->findOne());
    }

    public function testAnAdministratorInvitesAPersonAndRefusesARoleBeyondTheirOwn(): void
    {
        ConfigQuery::write('store_email', 'shop@test.com');
        $company = $this->company();
        $this->roleOf('manager', [CompanyPermission::ORDER_PLACE, CompanyPermission::ORDER_VIEW_OWN, CompanyPermission::QUOTE_REQUEST, CompanyPermission::BUYER_MANAGE]);
        $manager = $this->buyer($company, 'manager');
        $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $this->signInAs($manager);
        $page = $this->get('/account/company/buyers');
        $token = $this->tokenOf($page);

        $this->client->request('POST', '/account/company/invitations', ['_token' => $token, 'email' => 'new.buyer@example.com', 'role' => CompanyRoleCode::BUYER]);
        self::assertResponseRedirects('/account/company/buyers?success=invitation_sent');
        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerEvent(0)->getMessage(), 'To', 'new.buyer@example.com');

        $this->client->request('POST', '/account/company/invitations', ['_token' => $token, 'email' => 'other@example.com', 'role' => CompanyRoleCode::ADMINISTRATOR]);
        self::assertResponseRedirects('/account/company/buyers?error=beyond_your_rights');

        $this->client->request('POST', '/account/company/invitations', ['_token' => $token, 'email' => 'not-an-address', 'role' => CompanyRoleCode::BUYER]);
        self::assertResponseRedirects('/account/company/buyers?error=invalid_invitation');
    }

    public function testAnAdministratorWithLimitsIsOfferedThemAndInvitesWithThemByDefault(): void
    {
        ConfigQuery::write('store_email', 'shop@test.com');
        $company = $this->company();
        $capped = $this->customer();
        $this->companies->attach($company, $capped, CompanyRoleCode::ADMINISTRATOR, '300', '1000', 'month');
        $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $this->signInAs($capped);
        $page = $this->get('/account/company/buyers');
        $token = $this->tokenOf($page);

        self::assertSame('300', $page->filter('#invitation-order-limit')->attr('value'), 'The form starts from the limits of whoever invites.');
        self::assertSame('1000', $page->filter('#invitation-period-limit')->attr('value'));
        self::assertSame('month', $page->filter('#invitation-period option[selected]')->attr('value'));

        // Nothing typed: the limits of the one who invites.
        $this->client->request('POST', '/account/company/invitations', ['_token' => $token, 'email' => 'default@example.com', 'role' => CompanyRoleCode::BUYER]);
        self::assertResponseRedirects('/account/company/buyers?success=invitation_sent');
        $invitation = $this->getService(CompanyInvitationService::class)->read($this->tokenOfTheEmail());
        self::assertSame(0, bccomp('300', (string) $invitation?->orderLimit));
        self::assertSame(0, bccomp('1000', (string) $invitation?->periodLimit));
        self::assertSame('month', $invitation?->period);

        // Lower limits are kept, higher ones refused.
        $this->client->request('POST', '/account/company/invitations', ['_token' => $token, 'email' => 'lower@example.com', 'role' => CompanyRoleCode::BUYER, 'order_limit' => '100', 'period_limit' => '400', 'period' => 'month']);
        self::assertResponseRedirects('/account/company/buyers?success=invitation_sent');
        $lower = $this->getService(CompanyInvitationService::class)->read($this->tokenOfTheEmail());
        self::assertSame(0, bccomp('100', (string) $lower?->orderLimit));
        self::assertSame(0, bccomp('400', (string) $lower?->periodLimit));

        // A period limit with no period is counted over the period of the one who invites.
        $this->client->request('POST', '/account/company/invitations', ['_token' => $token, 'email' => 'noperiod@example.com', 'role' => CompanyRoleCode::BUYER, 'period_limit' => '400']);
        self::assertResponseRedirects('/account/company/buyers?success=invitation_sent');
        self::assertSame('month', $this->getService(CompanyInvitationService::class)->read($this->tokenOfTheEmail())?->period);

        $this->client->request('POST', '/account/company/invitations', ['_token' => $token, 'email' => 'higher@example.com', 'role' => CompanyRoleCode::BUYER, 'order_limit' => '301']);
        self::assertResponseRedirects('/account/company/buyers?error=beyond_your_rights');
    }

    public function testAnAdministratorWithNoLimitInvitesWithNoneAndMayGiveSome(): void
    {
        ConfigQuery::write('store_email', 'shop@test.com');
        $company = $this->company();
        $this->signInAs($this->buyer($company, CompanyRoleCode::ADMINISTRATOR));
        $page = $this->get('/account/company/buyers');
        $token = $this->tokenOf($page);

        self::assertSame('', (string) $page->filter('#invitation-order-limit')->attr('value'));

        $this->client->request('POST', '/account/company/invitations', ['_token' => $token, 'email' => 'free@example.com', 'role' => CompanyRoleCode::BUYER, 'order_limit' => '50']);

        self::assertResponseRedirects('/account/company/buyers?success=invitation_sent');
        $invitation = $this->getService(CompanyInvitationService::class)->read($this->tokenOfTheEmail());
        self::assertSame(0, bccomp('50', (string) $invitation?->orderLimit));
        self::assertNull($invitation?->periodLimit);
    }

    /**
     * The token in the link of the e-mail the last request sent: each request boots its own kernel,
     * which keeps what it sent.
     */
    private function tokenOfTheEmail(): string
    {
        $body = (string) self::getMailerMessage(0)?->getHtmlBody().(string) self::getMailerMessage(0)?->getTextBody();

        self::assertSame(1, preg_match('#/account/company/invitation/([A-Za-z0-9_\-.]+)#', $body, $matches), 'The e-mail carries the link of the invitation.');

        return $matches[1];
    }

    public function testASimpleBuyerCannotInvite(): void
    {
        $this->signInAs($this->buyer($this->company()));

        $this->client->request('POST', '/account/company/invitations', ['_token' => 'any', 'email' => 'new@example.com', 'role' => CompanyRoleCode::BUYER]);

        self::assertSame(403, $this->statusCode());
    }
}
