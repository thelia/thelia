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
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Order\Service\OrderTrackingUrlResolver;
use Thelia\Domain\Shipping\DTO\ShipmentLine;
use Thelia\Domain\Shipping\Service\OrderShipmentManager;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderShipment;
use Thelia\Model\OrderStatus;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * The parcels of an order on the customer's order page: an order shipped at once reads
 * as it always did, an order shipped in several shows one card per parcel with its own
 * number, carrier page and delivery note, and a parcel is never served to anyone but
 * the customer who ordered it.
 *
 * The page belongs to the theme, which ships as its own package: a theme older than the
 * shipment cards is reported as skipped rather than failed.
 */
final class AccountOrderShipmentsTest extends WebIntegrationTestCase
{
    private const SHIPMENT_CARD = 'FlexyBundle\\Components\\Organisms\\Shipment\\Base';

    private const CARRIER_CODE = 'AccountOrderShipmentsCarrier';

    private const TEMPLATE = 'https://carrier.example/track?parcel=%ID%';

    private ?CustomerSessionInjector $injector = null;

    protected function setUp(): void
    {
        if (!class_exists(self::SHIPMENT_CARD)) {
            self::markTestSkipped('The installed front-office theme does not show the parcels of an order.');
        }

        parent::setUp();

        $this->injector = new CustomerSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }

        ModuleConfigQuery::resetConfigCache();

        parent::tearDown();
    }

    public function testAnOrderShippedAtOnceReadsAsItAlwaysDid(): void
    {
        [$customer, $order, $first] = $this->paidOrderOfTwoLines();
        $this->shipped($order, [[$first, 3.0]], 'ONLY-PARCEL');

        $crawler = $this->openOrderAs($customer, $order);

        self::assertCount(0, $crawler->filter('[data-testid="order-shipment"]'), 'One parcel: no card, the tracker the page always had.');
        $link = $crawler->filter('[data-testid="delivery-tracking-link"]');
        self::assertCount(1, $link);
        self::assertSame('https://carrier.example/track?parcel=ONLY-PARCEL', $link->attr('href'));
        self::assertStringContainsString('ONLY-PARCEL', $crawler->filter('.DeliveryTracking')->text());
    }

    public function testAnOrderShippedInTwoParcelsShowsOneCardEach(): void
    {
        [$customer, $order, $first, $second] = $this->paidOrderOfTwoLines();
        $one = $this->shipped($order, [[$first, 3.0]], 'PARCEL-ONE');
        $two = $this->shipped($order, [[$second, 2.0]], 'PARCEL-TWO');

        $crawler = $this->openOrderAs($customer, $order);

        $cards = $crawler->filter('[data-testid="order-shipment"]');
        self::assertCount(2, $cards);
        self::assertCount(0, $crawler->filter('.DeliveryTracking'), 'Several parcels: the single tracker steps aside.');

        self::assertStringContainsString('Shipment 1 of 2', $cards->eq(0)->text());
        self::assertStringContainsString($one->getRef(), $cards->eq(0)->text());
        self::assertStringContainsString('Shippable widget', $cards->eq(0)->text());
        self::assertStringNotContainsString('Shippable gadget', $cards->eq(0)->text());
        self::assertSame('https://carrier.example/track?parcel=PARCEL-ONE', $cards->eq(0)->filter('[data-testid="shipment-tracking-link"]')->attr('href'));
        self::assertSame(
            $this->url('account_order_pdf_delivery_shipment', ['orderId' => $order->getId(), 'shipmentId' => $one->getId()]),
            $cards->eq(0)->filter('[data-testid="shipment-delivery-note-link"]')->attr('href'),
        );

        self::assertStringContainsString('Shipment 2 of 2', $cards->eq(1)->text());
        self::assertStringContainsString($two->getRef(), $cards->eq(1)->text());
        self::assertStringContainsString('Shippable gadget', $cards->eq(1)->text());
        self::assertSame('https://carrier.example/track?parcel=PARCEL-TWO', $cards->eq(1)->filter('[data-testid="shipment-tracking-link"]')->attr('href'));
    }

    public function testACancelledDraftIsNotAParcel(): void
    {
        [$customer, $order, $first, $second] = $this->paidOrderOfTwoLines();
        $manager = $this->getService(OrderShipmentManager::class);
        $manager->cancel($manager->open($order, [new ShipmentLine((int) $second->getId(), 1.0)]));
        $this->shipped($order, [[$first, 3.0]], 'REAL-PARCEL');

        $crawler = $this->openOrderAs($customer, $order);

        self::assertCount(0, $crawler->filter('[data-testid="order-shipment"]'), 'The cancelled draft does not make it two parcels.');
        self::assertStringContainsString('REAL-PARCEL', $crawler->filter('.DeliveryTracking')->text());
    }

    public function testTheDeliveryNoteOfAParcelIsServedToItsOwnerOnly(): void
    {
        [$customer, $order, $first] = $this->paidOrderOfTwoLines();
        $parcel = $this->shipped($order, [[$first, 1.0]], 'NOTE-PARCEL');
        $url = $this->url('account_order_pdf_delivery_shipment', ['orderId' => $order->getId(), 'shipmentId' => $parcel->getId()]);

        $this->injector?->setCustomer($customer);
        $this->client->request('GET', $url);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));

        [$strangerCustomer, $strangerOrder, $strangerLine] = $this->paidOrderOfTwoLines();
        $strangerParcel = $this->shipped($strangerOrder, [[$strangerLine, 1.0]], 'STRANGER');

        // The parcel of another customer's order, under one's own order id.
        $this->client->request('GET', $this->url('account_order_pdf_delivery_shipment', ['orderId' => $order->getId(), 'shipmentId' => $strangerParcel->getId()]));
        self::assertSame(404, $this->client->getResponse()->getStatusCode());

        // Another customer's order altogether.
        $this->injector?->setCustomer($strangerCustomer);
        $this->client->request('GET', $url);
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    private function openOrderAs(Customer $customer, Order $order): Crawler
    {
        $this->injector?->setCustomer($customer);
        $crawler = $this->client->request('GET', $this->url('account_order', ['orderId' => $order->getId()]));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return $crawler;
    }

    /**
     * @return array{0: Customer, 1: Order, 2: OrderProduct, 3: OrderProduct}
     */
    private function paidOrderOfTwoLines(): array
    {
        $factory = $this->factory();
        $customer = $factory->customer($factory->customerTitle());
        $order = $factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID, 'deliveryModuleCode' => $this->carrier()->getCode()]);

        return [
            $customer,
            $order,
            $factory->orderProduct($order, ['quantity' => 3.0, 'title' => 'Shippable widget']),
            $factory->orderProduct($order, ['quantity' => 2.0, 'title' => 'Shippable gadget']),
        ];
    }

    /**
     * @param list<array{0: OrderProduct, 1: float}> $lines
     */
    private function shipped(Order $order, array $lines, string $deliveryRef): OrderShipment
    {
        $manager = $this->getService(OrderShipmentManager::class);
        $shipmentLines = [];

        foreach ($lines as [$line, $quantity]) {
            $shipmentLines[] = new ShipmentLine((int) $line->getId(), $quantity);
        }

        return $manager->ship($manager->open($order, $shipmentLines, deliveryRef: $deliveryRef));
    }

    private function carrier(): Module
    {
        $existing = ModuleQuery::create()->filterByCode(self::CARRIER_CODE)->findOne($this->getPropelConnection());

        if (null !== $existing) {
            return $existing;
        }

        $module = new Module();
        $module
            ->setCode(self::CARRIER_CODE)
            ->setType(BaseModule::DELIVERY_MODULE_TYPE)
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->setFullNamespace(self::CARRIER_CODE.'\\'.self::CARRIER_CODE)
            ->save($this->getPropelConnection());
        ModuleConfigQuery::create()->setConfigValue($module->getId(), OrderTrackingUrlResolver::TRACKING_URL_CONFIG_KEY, self::TEMPLATE);

        return $module;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function url(string $route, array $parameters): string
    {
        return $this->getService(RouterInterface::class)->generate($route, $parameters);
    }

    /**
     * Built without createFixtureFactory(): that helper pushes a synthetic request when the
     * stack is empty, and it would then be the "main" request of the calls below, the one
     * the session, and therefore the logged-in customer, is read from.
     */
    private function factory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }
}
