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
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Domain\Order\Service\OrderTrackingUrlResolver;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * The carrier page following the parcel, as the customer reaches it from their account:
 * next to the tracking number on the order page, and on the card of a shipped order in
 * the order list.
 *
 * The pages belong to the theme, which ships as its own package: a theme older than the
 * tracking link is reported as skipped rather than failed.
 */
final class AccountOrderTrackingLinkTest extends WebIntegrationTestCase
{
    /**
     * Written as a string on purpose: `::class` on a missing import resolves silently in
     * the current namespace, and the guard would then skip the whole file for ever.
     */
    private const ORDER_CARD = 'FlexyBundle\\Components\\Organisms\\OrderCard\\Base';

    private const CARRIER_CODE = 'AccountOrderTrackingCarrier';

    private const TEMPLATE = 'https://carrier.example/track?parcel=%ID%';

    private ?CustomerSessionInjector $injector = null;

    protected function setUp(): void
    {
        if (!method_exists(self::ORDER_CARD, 'getTrackingUrl')) {
            self::markTestSkipped('The installed front-office theme does not show the parcel tracking link.');
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

    public function testTheOrderPageLinksTheTrackingNumberToTheCarrierPage(): void
    {
        [$customer, $order] = $this->orderOfANewCustomer(OrderStatus::CODE_SENT, '6A 12');

        $link = $this->openOrderAs($customer, $order)->filter('[data-testid="delivery-tracking-link"]');

        self::assertCount(1, $link);
        self::assertSame('https://carrier.example/track?parcel=6A%2012', $link->attr('href'));
        self::assertSame('_blank', $link->attr('target'));
    }

    public function testAnOrderWithoutTrackingNumberShowsNoLink(): void
    {
        [$customer, $order] = $this->orderOfANewCustomer(OrderStatus::CODE_SENT, null);

        $crawler = $this->openOrderAs($customer, $order);

        self::assertCount(0, $crawler->filter('[data-testid="delivery-tracking-link"]'));
    }

    public function testACancelledOrderShowsNoTracking(): void
    {
        [$customer, $order] = $this->orderOfANewCustomer(OrderStatus::CODE_CANCELED, '6A12');

        $crawler = $this->openOrderAs($customer, $order);

        self::assertCount(0, $crawler->filter('[data-testid="delivery-tracking-link"]'));
        self::assertStringNotContainsString('carrier.example', (string) $this->client->getResponse()->getContent());
    }

    public function testAnOrderInACustomStatusEquivalentToCanceledShowsNoTracking(): void
    {
        $this->factory()->orderStatus(['code' => 'cancelled_by_shop', 'equivalentCode' => OrderStatus::CODE_CANCELED]);
        [$customer, $order] = $this->orderOfANewCustomer('cancelled_by_shop', 'CANCEL1');

        $crawler = $this->openOrderAs($customer, $order);

        self::assertCount(0, $crawler->filter('[data-testid="delivery-tracking-link"]'));
        self::assertStringNotContainsString('carrier.example', (string) $this->client->getResponse()->getContent());
    }

    public function testTheGuestOrderPageOfACustomStatusEquivalentToCanceledShowsNoTracking(): void
    {
        $factory = $this->factory();
        $factory->orderStatus(['code' => 'guest_cancelled_by_shop', 'equivalentCode' => OrderStatus::CODE_CANCELED]);
        $guest = $factory->guestCustomer($factory->customerTitle());
        $order = $this->order($guest, $this->carrier(), 'guest_cancelled_by_shop', 'GUESTCANCEL1');

        $token = $this->getService(GuestOrderAccessService::class)->createToken($order);
        $crawler = $this->client->request('GET', '/order/track/'.$token);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        self::assertCount(0, $crawler->filter('[data-testid="delivery-tracking-link"]'));
        self::assertStringNotContainsString('carrier.example', (string) $this->client->getResponse()->getContent());
    }

    public function testTheOrderListOffersTheLinkOnShippedOrdersOnly(): void
    {
        $carrier = $this->carrier();
        $factory = $this->factory();
        $customer = $factory->customer($factory->customerTitle());
        $shipped = $this->order($customer, $carrier, OrderStatus::CODE_SENT, 'SHIPPED1');
        $this->order($customer, $carrier, OrderStatus::CODE_PROCESSING, 'NOTYET1');

        $this->injector?->setCustomer($customer);
        $crawler = $this->client->request('GET', $this->url('account_orders', []));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $links = $crawler->filter('[data-testid="order-card-tracking-link"]');
        self::assertCount(1, $links, 'Only the shipped order offers its tracking link.');
        self::assertSame('https://carrier.example/track?parcel=SHIPPED1', $links->attr('href'));
        self::assertSame('_blank', $links->attr('target'));
        self::assertCount(1, $links->filter('.sr-only'), 'A screen reader hears that the link opens a new tab.');
        self::assertNotNull($shipped->getId());
    }

    public function testTheOrderListOffersTheLinkOnACustomStatusEquivalentToSent(): void
    {
        $factory = $this->factory();
        $factory->orderStatus(['code' => 'handed_to_carrier', 'equivalentCode' => OrderStatus::CODE_SENT]);
        $customer = $factory->customer($factory->customerTitle());
        $this->order($customer, $this->carrier(), 'handed_to_carrier', 'HANDED1');

        $this->injector?->setCustomer($customer);
        $crawler = $this->client->request('GET', $this->url('account_orders', []));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $links = $crawler->filter('[data-testid="order-card-tracking-link"]');
        self::assertCount(1, $links, 'A custom status equivalent to "sent" is a shipped order.');
        self::assertSame('https://carrier.example/track?parcel=HANDED1', $links->attr('href'));
    }

    /**
     * A buyer without an account follows the order from the link of the confirmation
     * e-mail: the tracking link of the parcel is there too.
     */
    public function testTheGuestOrderPageLinksTheTrackingNumberToTheCarrierPage(): void
    {
        $factory = $this->factory();
        $guest = $factory->guestCustomer($factory->customerTitle());
        $order = $this->order($guest, $this->carrier(), OrderStatus::CODE_SENT, 'GUEST 1');

        $token = $this->getService(GuestOrderAccessService::class)->createToken($order);
        $crawler = $this->client->request('GET', '/order/track/'.$token);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $link = $crawler->filter('[data-testid="delivery-tracking-link"]');
        self::assertCount(1, $link);
        self::assertSame('https://carrier.example/track?parcel=GUEST%201', $link->attr('href'));
        self::assertStringContainsString('noreferrer', (string) $link->attr('rel'), 'The page address carries the access token: it must not reach the carrier.');
    }

    private function openOrderAs(Customer $customer, Order $order): Crawler
    {
        $this->injector?->setCustomer($customer);
        $crawler = $this->client->request('GET', $this->url('account_order', ['orderId' => $order->getId()]));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return $crawler;
    }

    /**
     * @return array{0: Customer, 1: Order}
     */
    private function orderOfANewCustomer(string $statusCode, ?string $trackingNumber): array
    {
        $factory = $this->factory();
        $customer = $factory->customer($factory->customerTitle());

        return [$customer, $this->order($customer, $this->carrier(), $statusCode, $trackingNumber)];
    }

    private function order(Customer $customer, Module $carrier, string $statusCode, ?string $trackingNumber): Order
    {
        $order = $this->factory()->order($customer, ['statusCode' => $statusCode, 'deliveryModuleCode' => $carrier->getCode()]);
        $order->setDeliveryRef($trackingNumber)->save($this->getPropelConnection());

        return $order;
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
     * stack is empty, and it would then be the "main" request of the calls below — the one
     * the session, and therefore the logged-in customer, is read from.
     */
    private function factory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }
}
