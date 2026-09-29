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

namespace Thelia\Tests\Integration\Domain\Customer;

use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\Customer\Service\CustomerAnonymizer;
use Thelia\Domain\Customer\Service\CustomerPersonalDataExporter;
use Thelia\Domain\CustomerList\PurchaseListFacade;
use Thelia\Model\CustomerListItemQuery;
use Thelia\Model\CustomerListQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * A customer's lists are personal data: they belong in the export, and they are
 * deleted on anonymization even though the anonymized customer row stays, which
 * is why the foreign key cascade is not enough.
 */
final class CustomerListsPersonalDataTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    public function testTheExportListsTheCustomerListsWithTheirLines(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(PurchaseListFacade::class)->create($customer, 'Restock', new ReferenceQuantityLines([new ReferenceQuantity('ABC', 3)]));

        $data = $this->getService(CustomerPersonalDataExporter::class)->export($customer);

        self::assertArrayHasKey('customer_lists', $data);
        $lists = $data['customer_lists'];
        self::assertCount(1, $lists);
        self::assertSame('Restock', $lists[0]['title']);
        self::assertSame([['reference' => 'ABC', 'quantity' => 3]], $lists[0]['items']);
    }

    public function testAnonymizationDeletesTheListsButKeepsTheCustomerRow(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $other = $this->factory->customer($this->factory->customerTitle());
        $facade = $this->getService(PurchaseListFacade::class);
        $list = $facade->create($customer, 'Private', new ReferenceQuantityLines([new ReferenceQuantity('ABC', 1)]));
        $kept = $facade->create($other, 'Someone else');

        $this->getService(CustomerAnonymizer::class)->anonymize($customer);

        self::assertNotNull(CustomerQuery::create()->findPk($customer->getId()));
        self::assertSame(0, CustomerListQuery::create()->filterByCustomerId($customer->getId())->count());
        self::assertSame(0, CustomerListItemQuery::create()->filterByCustomerListId($list->getId())->count());
        self::assertNotNull(CustomerListQuery::create()->findPk($kept->getId()));
    }
}
