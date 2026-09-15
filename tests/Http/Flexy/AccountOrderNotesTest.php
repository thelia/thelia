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
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * What of an order's history reaches the browser of the customer who owns the order.
 *
 * The API side of this is settled elsewhere (AccountOrderNotesApiTest): a note that is
 * marked visible travels, an internal note and any other kind of history entry do not.
 * What is checked here is the page — that the theme renders the notes it is given, that
 * it renders nothing at all when there are none, and that neither an internal note nor a
 * status change can be read off the HTML of the order page.
 *
 * The pages belong to the theme, which ships as its own package on its own release
 * cycle: a theme older than this component is reported as skipped rather than failed.
 */
final class AccountOrderNotesTest extends WebIntegrationTestCase
{
    /**
     * Written as a string on purpose: `::class` on a missing import resolves silently in
     * the current namespace, and the guard would then skip the whole file for ever.
     */
    private const COMPONENT = 'FlexyBundle\\Components\\Organisms\\OrderNotes\\Block';

    private const BLOCK = '[data-testid="order-notes-block"]';

    private const ENTRY = '[data-testid="order-notes-entry"]';

    private ?CustomerSessionInjector $injector = null;

    protected function setUp(): void
    {
        if (!class_exists(self::COMPONENT)) {
            self::markTestSkipped('The installed front-office theme has no order notes component.');
        }

        parent::setUp();

        $this->injector = new CustomerSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        // Resistant to the skip above: setUp returned before either was built.
        if (isset($this->injector)) {
            $this->injector->clear();
        }

        parent::tearDown();
    }

    public function testAVisibleNoteIsShownOnTheOrderPage(): void
    {
        [$customer, $order] = $this->orderOfANewCustomer();
        $this->note($order, 'Your parcel leaves tomorrow morning.', visibleToCustomer: true);

        $crawler = $this->openOrderAs($customer, $order);

        self::assertCount(1, $crawler->filter(self::BLOCK), 'The order page must carry the notes block.');
        self::assertCount(1, $crawler->filter(self::ENTRY));
        self::assertStringContainsString(
            'Your parcel leaves tomorrow morning.',
            $crawler->filter(self::ENTRY)->text(),
        );
    }

    /**
     * The note the shop wrote for itself. It is the same table, the same page and the
     * same customer: only the box that was left unticked keeps it out of the HTML.
     */
    public function testAnInternalNoteIsNeverShown(): void
    {
        [$customer, $order] = $this->orderOfANewCustomer();
        $this->note($order, 'Buyer sounded unhappy on the phone, watch this one.', visibleToCustomer: false);

        $crawler = $this->openOrderAs($customer, $order);

        self::assertCount(0, $crawler->filter(self::BLOCK), 'An internal note must not raise the block.');
        self::assertStringNotContainsString('sounded unhappy on the phone', $crawler->filter('body')->html());
    }

    /**
     * A status change is a history entry like a note is, and a module is free to mark it
     * visible — the column is one column. It must still never reach the page: the kind of
     * the entry is half of the condition, not a detail of it.
     */
    public function testAnEntryThatIsNotANoteIsNeverShown(): void
    {
        [$customer, $order] = $this->orderOfANewCustomer();

        $entry = new OrderHistory();
        $entry
            ->setOrderId($order->getId())
            ->setEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->setActorType(OrderHistoryActorType::SYSTEM->value)
            ->setPayload('{"from":"not_paid","to":"paid"}')
            ->setComment('Status moved to paid by the payment module.')
            ->setVisibleToCustomer(1)
            ->save($this->getPropelConnection());

        $crawler = $this->openOrderAs($customer, $order);

        self::assertCount(0, $crawler->filter(self::BLOCK));
        self::assertStringNotContainsString('Status moved to paid', $crawler->filter('body')->html());
    }

    /**
     * The notes are only as private as the order that carries them, and the order page of
     * somebody else's order answers exactly as one that does not exist — a 403 would
     * confirm the order is there to be found.
     */
    public function testAnotherCustomersOrderPageIsNotFound(): void
    {
        [, $order] = $this->orderOfANewCustomer();
        $this->note($order, 'Your parcel leaves tomorrow morning.', visibleToCustomer: true);

        $factory = $this->factory();
        $intruder = $factory->customer($factory->customerTitle());

        $this->injector?->setCustomer($intruder);
        $this->client->request('GET', $this->url('account_order', ['orderId' => $order->getId()]));

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    /**
     * The order of a shop that never wrote anything: the page is the page it always was,
     * with no heading and no empty state added to it.
     */
    public function testAnOrderWithoutANoteRendersWithoutTheBlock(): void
    {
        [$customer, $order] = $this->orderOfANewCustomer();

        $crawler = $this->openOrderAs($customer, $order);

        self::assertCount(0, $crawler->filter(self::BLOCK));
        self::assertCount(0, $crawler->filter(self::ENTRY));
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
    private function orderOfANewCustomer(): array
    {
        $factory = $this->factory();
        $customer = $factory->customer($factory->customerTitle());

        return [$customer, $factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID])];
    }

    private function note(Order $order, string $comment, bool $visibleToCustomer): void
    {
        $entry = new OrderHistory();
        $entry
            ->setOrderId($order->getId())
            ->setEventType(OrderHistoryEventType::NOTE->value)
            ->setActorType(OrderHistoryActorType::ADMIN->value)
            ->setActorLabel('shop-manager')
            ->setComment($comment)
            ->setVisibleToCustomer($visibleToCustomer ? 1 : 0)
            ->save($this->getPropelConnection());
    }

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
