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

namespace Thelia\Command\Import\Importer;

use Thelia\Command\Import\AbstractDemoImporter;
use Thelia\Command\Import\DemoImportContext;
use Thelia\Model\Address;
use Thelia\Model\Customer;

final class CustomersImporter extends AbstractDemoImporter
{
    public function priority(): int
    {
        return 100;
    }

    public function description(): string
    {
        return 'Customers';
    }

    public function import(DemoImportContext $context): void
    {
        $customer = new Customer();
        $customer->createOrUpdate(
            1,
            'thelia',
            'thelia',
            '5 rue rochon',
            '',
            '',
            '0102030405',
            '0601020304',
            '63000',
            'Clermont-Ferrand',
            64,
            'test@thelia.net',
            'thelia',
        );

        (new Address())
            ->setLabel('Address n°2')
            ->setTitleId(random_int(1, 3))
            ->setFirstname('thelia')
            ->setLastname('thelia')
            ->setAddress1('4 rue du Pensionnat Notre Dame de France')
            ->setAddress2('')
            ->setAddress3('')
            ->setCellphone('')
            ->setPhone('')
            ->setZipcode('43000')
            ->setCity('Le Puy-en-velay')
            ->setCountryId(64)
            ->setCustomer($customer)
            ->save($context->connection);

        (new Address())
            ->setLabel('Address n°3')
            ->setTitleId(random_int(1, 3))
            ->setFirstname('thelia')
            ->setLastname('thelia')
            ->setAddress1("43 rue d'Alsace-Lorrainee")
            ->setAddress2('')
            ->setAddress3('')
            ->setCellphone('')
            ->setPhone('')
            ->setZipcode('31000')
            ->setCity('Toulouse')
            ->setCountryId(64)
            ->setCustomer($customer)
            ->save($context->connection);

        $context->customers[] = $customer;
    }
}
