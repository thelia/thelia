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

/**
 * Creates demo customers and their addresses directly through Propel models.
 * Customer::createOrUpdate() is deliberately avoided: it opens its own
 * transaction, which would escape the command's wrapping transaction and
 * break --reset idempotency.
 */
final class CustomersImporter extends AbstractDemoImporter
{
    private const FRANCE_COUNTRY_ID = 64;
    private const DEMO_PASSWORD = 'thelia';

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
        $isFirst = true;
        foreach ($this->readCsv($context->dataDir.'customers.csv') as $data) {
            $titleId = (int) $data[0];

            $customer = new Customer();
            $customer->setTitleId($titleId);
            $customer->setFirstname($data[1]);
            $customer->setLastname($data[2]);
            $customer->setEmail($data[3]);
            $customer->setPassword(self::DEMO_PASSWORD);
            $customer->save($context->connection);

            (new Address())
                ->setCustomerId((int) $customer->getId())
                ->setLabel('Main address')
                ->setTitleId($titleId)
                ->setFirstname($data[1])
                ->setLastname($data[2])
                ->setAddress1($data[4])
                ->setAddress2('')
                ->setAddress3('')
                ->setZipcode($data[5])
                ->setCity($data[6])
                ->setPhone($data[7])
                ->setCellphone($data[8])
                ->setCountryId(self::FRANCE_COUNTRY_ID)
                ->setIsDefault(1)
                ->save($context->connection);

            if ($isFirst) {
                $this->addSecondaryAddresses($context, $customer);
                $isFirst = false;
            }

            $context->customers[] = $customer;
        }
    }

    private function addSecondaryAddresses(DemoImportContext $context, Customer $customer): void
    {
        (new Address())
            ->setCustomerId((int) $customer->getId())
            ->setLabel('Address n°2')
            ->setTitleId(1)
            ->setFirstname($customer->getFirstname())
            ->setLastname($customer->getLastname())
            ->setAddress1('4 rue du Pensionnat Notre Dame de France')
            ->setAddress2('')
            ->setAddress3('')
            ->setZipcode('43000')
            ->setCity('Le Puy-en-Velay')
            ->setCountryId(self::FRANCE_COUNTRY_ID)
            ->setIsDefault(0)
            ->save($context->connection);

        (new Address())
            ->setCustomerId((int) $customer->getId())
            ->setLabel('Address n°3')
            ->setTitleId(2)
            ->setFirstname($customer->getFirstname())
            ->setLastname($customer->getLastname())
            ->setAddress1("43 rue d'Alsace-Lorraine")
            ->setAddress2('')
            ->setAddress3('')
            ->setZipcode('31000')
            ->setCity('Toulouse')
            ->setCountryId(self::FRANCE_COUNTRY_ID)
            ->setIsDefault(0)
            ->save($context->connection);
    }
}
