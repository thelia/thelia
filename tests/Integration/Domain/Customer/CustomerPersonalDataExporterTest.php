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

use Symfony\Component\EventDispatcher\EventDispatcherInterface as ListenableEventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerPersonalDataExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\Service\CustomerPersonalDataExporter;
use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Model\Customer;
use Thelia\Model\Newsletter;
use Thelia\Model\OrderConsent;
use Thelia\Model\OrderProduct;
use Thelia\Model\TagElement;
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

        self::assertCoreSectionsComeFirst($personalData);

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

        // The address the consent was given from is personal data the shop keeps on
        // purpose, so the person can ask for it.
        self::assertCount(1, $order['consents']);
        self::assertSame('terms_and_conditions', $order['consents'][0]['code']);
        self::assertSame('I accept the terms and conditions of sale', $order['consents'][0]['title']);
        self::assertTrue($order['consents'][0]['accepted']);
        self::assertSame('203.0.113.7', $order['consents'][0]['ip_address']);
        self::assertNotNull($order['consents'][0]['answered_at']);

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

        self::assertCoreSectionsComeFirst($personalData);
        self::assertSame(['points' => 120], $personalData['loyalty']);
    }

    /**
     * A module adds its section through CustomerPersonalDataProviderInterface
     * or by listening to the event after core: the core sections stay, in
     * their order, and the module section comes on top of them.
     */
    public function testExportKeepsTheCoreSectionsWhenAModuleAddsOne(): void
    {
        $customer = $this->createCustomerWithHistory();

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        self::assertInstanceOf(ListenableEventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(
            TheliaEvents::CUSTOMER_PERSONAL_DATA_EXPORT,
            static fn (CustomerPersonalDataExportEvent $event) => $event->addSection('loyalty', ['points' => 120]),
            64,
        );

        $event = new CustomerPersonalDataExportEvent($customer);
        $dispatcher->dispatch($event, TheliaEvents::CUSTOMER_PERSONAL_DATA_EXPORT);

        $personalData = $event->getPersonalData();

        self::assertCoreSectionsComeFirst($personalData);
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

    /**
     * The tags an administrator put on a customer are internal markers, and the
     * file handed to that customer must carry no trace of them — not even an
     * empty section, which is why the clean-up lives in CustomerAnonymizer
     * rather than behind CustomerPersonalDataProviderInterface: the exporter
     * writes a section for every provider it holds, empty or not.
     */
    public function testExportCarriesNoTagSectionForATaggedCustomer(): void
    {
        $customer = $this->createCustomerWithHistory();
        $tag = $this->factory->tag(['label' => 'Bad payer']);
        $this->factory->tagElement($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $event = new CustomerPersonalDataExportEvent($customer);
        $this->getService(EventDispatcherInterface::class)->dispatch(
            $event,
            TheliaEvents::CUSTOMER_PERSONAL_DATA_EXPORT,
        );

        $personalData = $event->getPersonalData();

        self::assertCoreSectionsComeFirst($personalData);
        self::assertSame([], array_filter(
            array_keys($personalData),
            static fn (string $sectionName): bool => str_contains($sectionName, 'tag'),
        ));

        // The label itself, wherever it might have slipped in. Not a naive search
        // for "tag": "postage" contains it.
        self::assertStringNotContainsString('Bad payer', json_encode($personalData, \JSON_THROW_ON_ERROR));
    }

    /**
     * Modules add sections through CustomerPersonalDataProviderInterface, so the
     * export holds the core sections, in their order, and possibly more.
     *
     * @param array<string, mixed> $personalData
     */
    private static function assertCoreSectionsComeFirst(array $personalData): void
    {
        self::assertSame(
            CustomerPersonalDataExporter::CORE_SECTION_NAMES,
            \array_slice(array_keys($personalData), 0, \count(CustomerPersonalDataExporter::CORE_SECTION_NAMES)),
            'The core sections must all be exported, in their order, before any module section.',
        );
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

        (new OrderConsent())
            ->setOrderId($order->getId())
            ->setConsentCode('terms_and_conditions')
            ->setTitle('I accept the terms and conditions of sale')
            ->setAccepted(1)
            ->setIpAddress('203.0.113.7')
            ->save($this->getPropelConnection());

        return $customer;
    }
}
