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
use Thelia\Domain\Company\DTO\CompanyData;
use Thelia\Model\AddressQuery;
use Thelia\Model\CountryQuery;

/**
 * The address form saves the address and its sharing as one change: when the company refuses the
 * sharing, the page says so and the address is what it was. The test opens no transaction of its
 * own, since a rollback inside another one cannot be seen, and removes what it wrote.
 */
final class CompanyAddressSharingAtomicityTest extends CompanyAccountTestCase
{
    protected bool $useTransaction = false;

    public function testARefusedSharingLeavesTheAddressAsItWas(): void
    {
        $connection = $this->getPropelConnection();
        $company = $this->company('Atomic '.bin2hex(random_bytes(4)));
        $administrator = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);

        try {
            $this->signInAs($administrator);
            $page = $this->get('/account/address/new');
            $this->submit($page, 'Head office', 'Paris', true);
            $office = AddressQuery::create()->filterByCompanyId($company->getId())->findOne();
            self::assertNotNull($office);

            // The company is invoiced at this address: it cannot stop being shared.
            $this->companies->update($company, new CompanyData(name: (string) $company->getName(), siret: '12345678901234', vatNumber: 'FR12345678901', billingAddressId: (int) $office->getId()));

            $this->submit($this->get('/account/address/'.$office->getId()), 'Head office', 'Lyon', false);

            self::assertSame('/account/address/'.$office->getId(), parse_url((string) $this->client->getResponse()->headers->get('Location'), \PHP_URL_PATH), 'The page reports the failure.');
            // Read from the database, not from the pool: the object the request changed stays in memory.
            $stored = $connection->query('SELECT `company_id`, `city` FROM `address` WHERE `id` = '.(int) $office->getId())->fetch(\PDO::FETCH_NUM);
            self::assertSame([(int) $company->getId(), 'Paris'], [(int) $stored[0], $stored[1]], 'The address is still shared and the new city was not kept: the page said it failed, so nothing was saved.');
        } finally {
            $this->signInAs(null);
            $connection->exec('UPDATE `company` SET `billing_address_id` = NULL WHERE `id` = '.(int) $company->getId());
            $connection->exec('DELETE FROM `address` WHERE `customer_id` = '.(int) $administrator->getId());
            $connection->exec('DELETE FROM `company` WHERE `id` = '.(int) $company->getId());
            $connection->exec('DELETE FROM `customer` WHERE `id` = '.(int) $administrator->getId());
        }
    }

    private function submit(\Symfony\Component\DomCrawler\Crawler $page, string $label, string $city, bool $share): void
    {
        $form = $page->filter('form[action*="/account/address/"]')->form();
        $country = CountryQuery::create()->filterByVisible(1)->findOne();

        foreach ([
            'label' => $label,
            'firstname' => 'Pat',
            'lastname' => 'Buyer',
            'address1' => '1 rue du Quai',
            'zipcode' => '75001',
            'city' => $city,
            'country' => (string) $country?->getId(),
        ] as $field => $value) {
            $name = 'flexybundle_address_edit_form['.$field.']';

            if ($form->has($name)) {
                $form[$name] = $value;
            }
        }

        $shareField = 'flexybundle_address_edit_form[share_with_company]';

        if ($share) {
            $form[$shareField]->tick();
        } else {
            $form[$shareField]->untick();
        }

        $this->client->submit($form);
    }
}
