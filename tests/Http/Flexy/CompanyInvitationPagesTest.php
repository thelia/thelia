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

use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Company\Service\CompanyInvitationService;
use Thelia\Model\Company;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

/**
 * The page the link of the invitation e-mail opens: whoever holds a valid link chooses a password
 * or signs in, and joins; whatever is not a valid link is the same page, with the same status.
 */
final class CompanyInvitationPagesTest extends CompanyAccountTestCase
{
    private const FORM = 'flexybundle_form_customer_register_form';

    private Company $company;

    private Customer $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->company('Acme Corp');
        $this->administrator = $this->buyer($this->company, CompanyRoleCode::ADMINISTRATOR);
    }

    public function testAnAlteredLinkAndAnUnknownOneAreTheSameInvalidPage(): void
    {
        $valid = $this->tokenFor('invited@example.com');

        $pages = [];

        foreach ([$valid.'x', strrev($valid), 'not-a-token', str_repeat('a', 80)] as $token) {
            $page = $this->get('/account/company/invitation/'.$token);

            self::assertSame(404, $this->statusCode());
            self::assertCount(1, $page->filter('[data-testid="company-invitation-invalid"]'));
            $pages[] = $page->filter('[data-testid="company-invitation"]')->html();
        }

        self::assertCount(1, array_unique($pages), 'Nothing says which way the link is wrong.');
    }

    public function testAVisitorWithoutAccountIsOfferedTheSignUpOfTheAddressInvited(): void
    {
        $token = $this->tokenFor('invited@example.com');

        $page = $this->get('/account/company/invitation/'.$token);

        self::assertSame(200, $this->statusCode());
        self::assertStringContainsString('Acme Corp', $page->filter('[data-testid="company-invitation"]')->text());
        self::assertSame('invited@example.com', $page->filter('input[name="'.self::FORM.'[email]"]')->attr('value'));
        self::assertNotNull($page->filter('input[name="'.self::FORM.'[email]"][disabled]')->getNode(0), 'The address is shown, not editable.');
    }

    public function testTheSignUpCreatesTheAccountOfTheAddressInvitedAndJoinsTheCompany(): void
    {
        $token = $this->tokenFor('invited@example.com');
        $page = $this->get('/account/company/invitation/'.$token);

        $this->client->request('POST', '/account/company/invitation/'.$token.'/register', [self::FORM => [
            'firstname' => 'Ines',
            'lastname' => 'Invited',
            // The address of the invitation wins over any other.
            'email' => 'someone.else@example.com',
            'password' => 'Zq7!vK2#mX9$pL4wR',
            'success_url' => '/account/company?success=joined',
            'error_url' => '',
            '_token' => (string) $page->filter('input[name="'.self::FORM.'[_token]"]')->attr('value'),
        ]]);

        $customer = CustomerQuery::create()->findOneByEmail('invited@example.com');
        self::assertNotNull($customer, 'The account of the address invited was created.');
        self::assertNull(CustomerQuery::create()->findOneByEmail('someone.else@example.com'));
        self::assertSame((int) $this->company->getId(), CompanyCustomerQuery::create()->filterByCustomerId($customer->getId())->findOne()?->getCompanyId());
    }

    public function testAFailedSignUpJoinsNothing(): void
    {
        $token = $this->tokenFor('invited@example.com');
        $page = $this->get('/account/company/invitation/'.$token);

        $this->client->request('POST', '/account/company/invitation/'.$token.'/register', [self::FORM => [
            'firstname' => 'Ines',
            'lastname' => 'Invited',
            'email' => 'invited@example.com',
            'password' => '',
            'success_url' => '/account/company?success=joined',
            'error_url' => '',
            '_token' => (string) $page->filter('input[name="'.self::FORM.'[_token]"]')->attr('value'),
        ]]);

        self::assertResponseRedirects('/account/company/invitation/'.$token);
        self::assertNull(CustomerQuery::create()->findOneByEmail('invited@example.com'));
    }

    public function testAnExistingAccountIsAskedToSignIn(): void
    {
        $this->customer('invited@example.com');
        $token = $this->tokenFor('invited@example.com');

        $page = $this->get('/account/company/invitation/'.$token);

        self::assertSame(200, $this->statusCode());
        self::assertCount(0, $page->filter('input[name="'.self::FORM.'[email]"]'), 'No second account for the same address.');
        self::assertStringContainsString('sign in', $page->filter('[data-testid="company-invitation"]')->text());
    }

    public function testTheAccountOfTheAddressInvitedAcceptsWithAButton(): void
    {
        $invited = $this->customer('invited@example.com');
        $token = $this->tokenFor('invited@example.com');
        $this->signInAs($invited);

        $page = $this->get('/account/company/invitation/'.$token);
        self::assertSame(200, $this->statusCode());
        $this->client->request('POST', '/account/company/invitation/'.$token, ['_token' => $this->tokenOf($page)]);

        self::assertResponseRedirects('/account/company?success=joined');
        self::assertSame((int) $this->company->getId(), CompanyCustomerQuery::create()->filterByCustomerId($invited->getId())->findOne()?->getCompanyId());
    }

    public function testAnotherAccountCannotTakeTheInvitation(): void
    {
        $other = $this->customer('other@example.com');
        $token = $this->tokenFor('invited@example.com');
        $this->signInAs($other);

        $page = $this->get('/account/company/invitation/'.$token);
        self::assertCount(1, $page->filter('[data-testid="company-invitation-other-account"]'));

        $this->client->request('POST', '/account/company/invitation/'.$token, ['_token' => 'any']);

        self::assertSame(403, $this->statusCode(), 'The token of the action is the one of the page, not a guess.');
        self::assertNull(CompanyCustomerQuery::create()->filterByCustomerId($other->getId())->findOne());
    }

    public function testALinkAlreadyUsedIsTheInvalidPage(): void
    {
        $invited = $this->customer('invited@example.com');
        $token = $this->tokenFor('invited@example.com');
        $this->buyer($this->company, CompanyRoleCode::BUYER, $invited);

        $this->get('/account/company/invitation/'.$token);

        self::assertSame(404, $this->statusCode());
    }

    private function tokenFor(string $email): string
    {
        return $this->getService(CompanyInvitationService::class)->createToken($this->company, $email, CompanyRoleCode::BUYER, $this->administrator, null, null, null);
    }
}
