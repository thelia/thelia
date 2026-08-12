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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerPersonalDataExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\Service\CustomerPersonalDataExporter;
use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Model\Customer;
use Thelia\Model\Newsletter;
use Thelia\Model\OrderProduct;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class CustomerPersonalDataExporterTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    public function testExportCollectsTheWholeCustomerFile(): void
    {
        $customer = $this->createCustomerWithHistory();

        $event = new CustomerPersonalDataExportEvent($customer);
        $this->getService(EventDispatcherInterface::class)->dispatch(
            $event,
            TheliaEvents::CUSTOMER_PERSONAL_DATA_EXPORT,
        );

        $personalData = $event->getPersonalData();

        self::assertSame(CustomerPersonalDataExporter::CORE_SECTION_NAMES, array_keys($personalData));

        self::assertSame('Exported', $personalData['customer']['firstname']);
        self::assertSame('exporter-subject@test.com', $personalData['customer']['email']);
        self::assertArrayNotHasKey('password', $personalData['customer']);

        self::assertCount(1, $personalData['addresses']);
        self::assertSame('Exported', $personalData['addresses'][0]['firstname']);

        self::assertCount(1, $personalData['orders']);
        $order = $personalData['orders'][0];
        self::assertNotNull($order['reference']);
        self::assertSame(12.5, (float) $order['postage']);
        self::assertNotNull($order['invoice_address']);
        self::assertNotNull($order['delivery_address']);
        self::assertCount(1, $order['products']);
        self::assertSame('REF-EXPORTER', $order['products'][0]['product_reference']);

        self::assertNotEmpty($personalData['carts']);

        self::assertNotNull($personalData['newsletter']);
        self::assertSame('exporter-subject@test.com', $personalData['newsletter']['email']);
    }

    public function testExportIncludesTheSectionsDeclaredByModules(): void
    {
        $customer = $this->createCustomerWithHistory();

        $provider = new class implements CustomerPersonalDataProviderInterface {
            public function getPersonalDataSectionName(): string
            {
                return 'loyalty';
            }

            public function exportPersonalData(Customer $customer): array
            {
                return ['points' => 120];
            }

            public function anonymizePersonalData(Customer $customer): void
            {
            }
        };

        $personalData = (new CustomerPersonalDataExporter([$provider]))->export($customer);

        self::assertArrayHasKey('loyalty', $personalData);
        self::assertSame(['points' => 120], $personalData['loyalty']);
    }

    public function testExportRejectsAProviderReusingACoreSectionName(): void
    {
        $customer = $this->createCustomerWithHistory();

        $provider = new class implements CustomerPersonalDataProviderInterface {
            public function getPersonalDataSectionName(): string
            {
                return 'orders';
            }

            public function exportPersonalData(Customer $customer): array
            {
                return [];
            }

            public function anonymizePersonalData(Customer $customer): void
            {
            }
        };

        $this->expectException(\LogicException::class);

        (new CustomerPersonalDataExporter([$provider]))->export($customer);
    }

    private function createCustomerWithHistory(): Customer
    {
        $customer = $this->factory->customer(
            $this->factory->customerTitle(),
            [
                'firstname' => 'Exported',
                'lastname' => 'Subject',
                'email' => 'exporter-subject@test.com',
            ],
        );

        $this->factory->address($customer, null, null, ['firstname' => 'Exported']);

        $newsletter = new Newsletter();
        $newsletter
            ->setEmail('exporter-subject@test.com')
            ->setFirstname('Exported')
            ->setLastname('Subject')
            ->save($this->getPropelConnection());

        $order = $this->factory->order($customer, ['postage' => '12.5', 'postageTax' => '2.5']);

        $orderProduct = new OrderProduct();
        $orderProduct
            ->setOrderId($order->getId())
            ->setProductRef('REF-EXPORTER')
            ->setProductSaleElementsRef('REF-EXPORTER-PSE')
            ->setTitle('Product')
            ->setQuantity(1)
            ->setPrice('99.99')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());

        return $customer;
    }
}
