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

use Symfony\Component\DomCrawler\Crawler;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\AddressQuery;
use Thelia\Model\CountryQuery;

/**
 * The address book of a buyer: the addresses of the company are a group of their own, changed
 * only by their creator and by the buyers whose role manages them, and any buyer shares what they
 * created. A customer who belongs to no company has the address book they always had.
 */
final class CompanyAddressBookTest extends CompanyAccountTestCase
{
    public function testAColleagueSeesTheSharedAddressInTheCompanyGroupAndNeverThePersonalOnes(): void
    {
        $company = $this->company();
        $creator = $this->buyer($company);
        $colleague = $this->buyer($company);
        $warehouse = $this->sharedAddress($creator, $company, 'Warehouse');
        $home = $this->sharedAddress($creator, null, 'Cellar');
        $mine = $this->sharedAddress($colleague, null, 'Mine');
        $this->signInAs($colleague);

        $page = $this->get('/account/addresses');

        self::assertSame(200, $this->statusCode());
        $text = $page->filter('body')->text();
        self::assertStringContainsString('Warehouse', $text);
        self::assertStringContainsString('Mine', $text);
        self::assertStringNotContainsString('Cellar', $text);
        self::assertCount(1, $page->filter('[data-testid="company-addresses"]'));
        self::assertCount(1, $page->filter('[data-testid="address-shared"]'));
        self::assertCount(0, $page->filter('a[href="/account/address/'.$warehouse->getId().'"]'), 'The colleague cannot modify the shared address.');
        self::assertCount(1, $page->filter('a[href="/account/address/'.$mine->getId().'"]'));
        self::assertNotNull($home->getId());
    }

    public function testTheEditPageOfASharedAddressIsForItsCreatorAndTheAdministratorOnly(): void
    {
        $first = $this->company('Acme');
        $second = $this->company('Globex');
        $creator = $this->buyer($first);
        $colleague = $this->buyer($first);
        $admin = $this->buyer($first, CompanyRoleCode::ADMINISTRATOR);
        $stranger = $this->buyer($second, CompanyRoleCode::ADMINISTRATOR);
        $warehouse = $this->sharedAddress($creator, $first, 'Warehouse');
        $path = '/account/address/'.$warehouse->getId();

        foreach ([[$creator, 200], [$admin, 200], [$colleague, 404], [$stranger, 404]] as [$who, $expected]) {
            $this->signInAs($who);
            $this->get($path);

            self::assertSame($expected, $this->statusCode(), $who->getEmail().' on '.$path);
        }
    }

    public function testAColleagueCannotDeleteNorMakeDefaultTheSharedAddressButTheAdministratorDeletesIt(): void
    {
        $company = $this->company();
        $creator = $this->buyer($company);
        $colleague = $this->buyer($company);
        $admin = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $warehouse = $this->sharedAddress($creator, $company, 'Warehouse');
        $this->sharedAddress($colleague, null, 'Mine');

        $this->signInAs($colleague);
        $token = $this->tokenOf($this->get('/account/addresses'));
        $this->client->request('POST', '/account/address/delete/'.$warehouse->getId(), ['_token' => $token]);
        self::assertResponseRedirects('/account/addresses?error=1');
        $this->client->request('POST', '/account/address/default/'.$warehouse->getId(), ['_token' => $token]);
        self::assertResponseRedirects('/account/addresses?error=1');
        self::assertNotNull(AddressQuery::create()->findPk($warehouse->getId()));

        $this->signInAs($admin);
        $token = $this->tokenOf($this->get('/account/addresses'));
        $this->client->request('POST', '/account/address/delete/'.$warehouse->getId(), ['_token' => $token]);
        self::assertResponseRedirects('/account/addresses?delete_success=1');
        self::assertNull(AddressQuery::create()->findPk($warehouse->getId()));
    }

    public function testAnAddressOfAnotherCompanyIsNeverInTheBook(): void
    {
        $first = $this->company('Acme');
        $second = $this->company('Globex');
        $buyer = $this->buyer($first);
        $creatorOfSecond = $this->buyer($second);
        $this->sharedAddress($creatorOfSecond, $second, 'Depot of Globex');
        $this->signInAs($buyer);

        $page = $this->get('/account/addresses');

        self::assertStringNotContainsString('Depot of Globex', $page->filter('body')->text());
        self::assertCount(0, $page->filter('[data-testid="company-addresses"]'));
    }

    public function testAnySimpleBuyerSharesTheAddressTheyCreateAndTakesItBack(): void
    {
        $company = $this->company();
        $buyer = $this->buyer($company);
        $colleague = $this->buyer($company);
        $this->signInAs($buyer);

        $page = $this->get('/account/address/new');
        self::assertCount(1, $page->filter('input[name$="[share_with_company]"]'), 'A buyer is offered the box.');
        $this->submitAddress($page, 'Dock', true);

        $dock = AddressQuery::create()->filterByLabel('Dock')->findOne();
        self::assertNotNull($dock);
        self::assertSame((int) $company->getId(), $dock->getCompanyId());
        self::assertSame((int) $buyer->getId(), $dock->getCustomerId());

        $this->signInAs($colleague);
        self::assertStringContainsString('Dock', $this->get('/account/addresses')->filter('body')->text());

        $this->signInAs($buyer);
        $edit = $this->get('/account/address/'.$dock->getId());
        self::assertTrue($edit->filter('input[name$="[share_with_company]"]')->getNode(0)?->hasAttribute('checked'), 'The box is ticked for an address that is shared.');
        $this->submitAddress($edit, 'Dock', false);

        self::assertNull(AddressQuery::create()->findPk($dock->getId())?->getCompanyId());
    }

    public function testACustomerWithoutCompanyHasNoShareBoxAndNoGroups(): void
    {
        $customer = $this->customer();
        $this->sharedAddress($customer, null, 'Cellar');
        $this->signInAs($customer);

        $book = $this->get('/account/addresses');
        $form = $this->get('/account/address/new');

        self::assertCount(0, $book->filter('[data-testid="company-addresses"], [data-testid="personal-addresses"]'));
        self::assertCount(0, $form->filter('input[name$="[share_with_company]"]'));
    }

    private function submitAddress(Crawler $page, string $label, bool $share): void
    {
        $form = $page->filter('form[action*="/account/address/"]')->form();
        $country = CountryQuery::create()->filterByVisible(1)->findOne();
        $values = [
            'label' => $label,
            'firstname' => 'Pat',
            'lastname' => 'Buyer',
            'address1' => '1 rue du Quai',
            'zipcode' => '75001',
            'city' => 'Paris',
            'country' => (string) $country?->getId(),
        ];

        foreach ($values as $field => $value) {
            $name = 'flexybundle_address_edit_form['.$field.']';

            if ($form->has($name)) {
                $form[$name] = $value;
            }
        }

        $shareField = 'flexybundle_address_edit_form[share_with_company]';

        if ($share) {
            $form[$shareField]->tick();
        } elseif ($form->has($shareField)) {
            $form[$shareField]->untick();
        }

        $this->client->submit($form);
    }
}
