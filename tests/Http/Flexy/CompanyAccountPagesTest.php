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
use Thelia\Model\CompanyQuery;
use Thelia\Model\CountryQuery;

/**
 * "My company" in the account: the menu entry, the pages and what each role may do on them. A
 * customer who belongs to no company has neither the link nor the pages.
 */
final class CompanyAccountPagesTest extends CompanyAccountTestCase
{
    public function testTheMenuHasNoCompanyEntryForACustomerWithoutCompany(): void
    {
        $this->signInAs($this->customer());

        $page = $this->get('/account');

        self::assertSame(200, $this->statusCode());
        self::assertNotContains('/account/company', $this->subheaderLinks($page));
    }

    public function testTheMenuHasTheCompanyEntryForABuyer(): void
    {
        $this->signInAs($this->buyer($this->company()));

        $page = $this->get('/account');

        self::assertContains('/account/company', $this->subheaderLinks($page));
    }

    public function testTheCompanyPageAnswersNotFoundToACustomerWithoutCompany(): void
    {
        $this->signInAs($this->customer());

        foreach (['/account/company', '/account/company/buyers'] as $path) {
            $this->get($path);

            self::assertSame(404, $this->statusCode(), $path);
        }
    }

    public function testASimpleBuyerReadsTheCompanyAndHasNoForm(): void
    {
        $this->signInAs($this->buyer($this->company('Acme Corp')));

        $page = $this->get('/account/company');

        self::assertSame(200, $this->statusCode());
        self::assertStringContainsString('Acme Corp', $page->filter('[data-testid="company-details"]')->text());
        self::assertCount(0, $page->filter('[data-testid="company-form"]'));
        self::assertSame(['/account/company'], $page->filter('nav[aria-label="My company"] a')->each(static fn ($link): string => (string) $link->attr('href')), 'A simple buyer has no tab to the buyers.');
    }

    public function testAnAdministratorEditsTheCompany(): void
    {
        $company = $this->company('Acme Corp');
        $admin = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $this->signInAs($admin);

        $page = $this->get('/account/company');
        self::assertCount(1, $page->filter('[data-testid="company-form"]'));

        $this->client->request('POST', '/account/company', [
            '_token' => $this->tokenOf($page),
            'name' => 'Acme Industries',
            'siret' => '12345678901234',
            'vat_number' => 'FR123',
            'notification_email' => 'orders@acme.example',
        ]);

        self::assertResponseRedirects('/account/company?success=company_updated');
        $stored = CompanyQuery::create()->findPk($company->getId());
        self::assertSame('Acme Industries', $stored?->getName());
        self::assertSame('orders@acme.example', $stored->getNotificationEmail());
    }

    public function testIdentifiersTheRulesOfAnAddressRefuseAreShownOnTheCompanyScreenAndNothingIsSaved(): void
    {
        $company = $this->company('Acme Corp');
        $admin = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $billing = $this->sharedAddress($admin, $company, 'Billing');
        $billing->setCountryId(CountryQuery::create()->filterByIsoalpha2('FR')->findOne()?->getId())->save($this->getPropelConnection());
        $company->setSiret('30326504500003')->setBillingAddressId((int) $billing->getId())->save($this->getPropelConnection());
        $this->signInAs($admin);
        $token = $this->tokenOf($this->get('/account/company'));

        $this->client->request('POST', '/account/company', ['_token' => $token, 'name' => 'Acme Corp', 'siret' => '12345678900011', 'vat_number' => 'FR12345678901']);

        self::assertSame(422, $this->statusCode());
        $page = $this->client->getCrawler();
        self::assertStringContainsString('The checksum of this SIRET number is invalid', $page->filter('#company-siret-error')->text());
        self::assertStringContainsString('The checksum of this VAT number is invalid', $page->filter('#company-vat-error')->text());
        self::assertSame('12345678900011', $page->filter('input[name="siret"]')->attr('value'), 'What was typed is given back.');
        self::assertSame('30326504500003', CompanyQuery::create()->findPk($company->getId())?->getSiret());

        $this->client->request('POST', '/account/company', ['_token' => $token, 'name' => 'Acme Corp', 'siret' => '30326504500003', 'vat_number' => 'FR40303265045']);

        self::assertResponseRedirects('/account/company?success=company_updated');
    }

    public function testTheIdentifiersOfACompanyWhoseOrdersWereLeftWithoutVatCannotBeChanged(): void
    {
        $company = $this->company('Acme Corp');
        $company->setSiret('11111111111111')->setVatNumber('FR111')->save($this->getPropelConnection());
        $admin = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $order = $this->orderPlacedFor($company, $admin);
        $order->getOrderAddressRelatedByInvoiceOrderAddressId()?->setVatExempted(1)->save($this->getPropelConnection());
        $this->signInAs($admin);
        $token = $this->tokenOf($this->get('/account/company'));

        $this->client->request('POST', '/account/company', ['_token' => $token, 'name' => 'Acme Corp', 'siret' => '22222222222222', 'vat_number' => 'FR111']);

        self::assertResponseRedirects('/account/company?error=identifiers_locked');
        self::assertSame('11111111111111', CompanyQuery::create()->findPk($company->getId())?->getSiret());

        $this->client->request('POST', '/account/company', ['_token' => $token, 'name' => 'Acme Industries', 'siret' => '11111111111111', 'vat_number' => 'FR111']);

        self::assertResponseRedirects('/account/company?success=company_updated');
    }

    public function testASimpleBuyerCannotEditTheCompany(): void
    {
        $company = $this->company('Acme Corp');
        $this->signInAs($this->buyer($company));

        // The permission is judged before the token: a forged request gets no further with a good one.
        $this->client->request('POST', '/account/company', ['_token' => 'any', 'name' => 'Hijacked']);

        self::assertSame(403, $this->statusCode());
        self::assertSame('Acme Corp', CompanyQuery::create()->findPk($company->getId())?->getName());
    }

    public function testTheBuyersPageAnswersAnAdministratorAndRefusesASimpleBuyer(): void
    {
        $company = $this->company();
        $admin = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyer($company);

        $this->signInAs($admin);
        $page = $this->get('/account/company/buyers');
        self::assertSame(200, $this->statusCode());
        self::assertCount(2, $page->filter('[data-testid="company-buyer"]'));

        $this->signInAs($buyer);
        $this->get('/account/company/buyers');
        self::assertSame(403, $this->statusCode());
    }
}
