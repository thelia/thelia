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

use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * The return of an order, as the Flexy theme lets a customer ask for it and follow it.
 *
 * What is checked here is what the browser is allowed to obtain, not what the domain
 * computes: the button shows only inside the return window, a quantity above the one
 * ordered is refused whatever the form said, another customer's order and another
 * customer's return document are out of reach, and a shop with the feature off has no
 * return pages at all.
 *
 * The pages belong to the theme, which ships as its own package on its own release
 * cycle: a theme older than these routes is reported as skipped rather than failed.
 */
final class AccountOrderReturnTest extends WebIntegrationTestCase
{
    private const FORM_ROUTE = 'account_order_return_new';

    private const TRACKING_ROUTE = 'account_return';

    private const DOCUMENT_ROUTE = 'account_return_pdf';

    private ?CustomerSessionInjector $injector = null;

    private ?string $previousEnabled = null;

    private ?string $previousWindow = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (null === $this->getService(RouterInterface::class)->getRouteCollection()->get(self::FORM_ROUTE)) {
            self::markTestSkipped('The installed front-office theme has no order return pages.');
        }

        $this->previousEnabled = ConfigQuery::read(ReturnEligibilityChecker::ENABLED_CONFIG_KEY);
        $this->previousWindow = ConfigQuery::read(ReturnEligibilityChecker::WINDOW_CONFIG_KEY);
        $this->enableReturns('14');

        $this->injector = new CustomerSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        $this->injector?->clear();
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, (string) $this->previousEnabled);
        ConfigQuery::write(ReturnEligibilityChecker::WINDOW_CONFIG_KEY, (string) $this->previousWindow);

        parent::tearDown();
    }

    public function testTheOrderPageOffersAReturnInsideTheWindow(): void
    {
        [$customer, $order] = $this->paidOrderWithOneLine(2.0, daysAgo: 3);

        $this->injector?->setCustomer($customer);
        $crawler = $this->client->request('GET', $this->url('account_order', ['orderId' => $order->getId()]));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(
            1,
            $crawler->filter('a[href$="/order/'.$order->getId().'/return"]'),
            'The order page of a recent paid order must offer to open a return.',
        );
    }

    public function testTheOrderPageOffersNoReturnOutsideTheWindow(): void
    {
        [$customer, $order] = $this->paidOrderWithOneLine(2.0, daysAgo: 60);

        $this->injector?->setCustomer($customer);
        $crawler = $this->client->request('GET', $this->url('account_order', ['orderId' => $order->getId()]));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(
            0,
            $crawler->filter('a[href$="/order/'.$order->getId().'/return"]'),
            'An order whose return window has closed must not offer to open a return.',
        );

        // And the page behind the button is gone with it, not merely unlinked.
        $this->client->request('GET', $this->url(self::FORM_ROUTE, ['orderId' => $order->getId()]));
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testAPartialReturnIsOpened(): void
    {
        [$customer, $order, $line] = $this->paidOrderWithOneLine(3.0, daysAgo: 1);

        $this->injector?->setCustomer($customer);
        $this->submitReturn($order, $line, '1');

        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        $return = OrderReturnQuery::create()->filterByOrderId($order->getId())->findOne();

        self::assertNotNull($return, 'The return must have been written.');
        self::assertSame($customer->getId(), $return->getCustomerId());
        self::assertSame(
            OrderReturnStatus::CODE_REQUESTED,
            $return->getOrderReturnStatus()?->getCode(),
            'A return opened by a customer starts as requested.',
        );
        self::assertCount(1, $return->getOrderReturnLines());
        self::assertSame(1.0, (float) $return->getOrderReturnLines()->getFirst()->getQuantity());
        self::assertStringContainsString(
            (string) $return->getId(),
            (string) $this->client->getResponse()->headers->get('Location'),
            'The customer lands on the tracking page of the return just opened.',
        );
    }

    public function testAQuantityAboveTheOrderedOneIsRefused(): void
    {
        [$customer, $order, $line] = $this->paidOrderWithOneLine(2.0, daysAgo: 1);

        $this->injector?->setCustomer($customer);

        // The form offers at most the ordered quantity; the browser is made to ask for more.
        $this->submitReturn($order, $line, '9');

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            '/order/'.$order->getId().'/return',
            (string) $this->client->getResponse()->headers->get('Location'),
            'A refused request comes back to the form.',
        );
        self::assertNull(
            OrderReturnQuery::create()->filterByOrderId($order->getId())->findOne(),
            'Nothing may be written when the quantity exceeds what was ordered.',
        );
    }

    public function testTheTrackingPageShowsTheReturn(): void
    {
        [$customer, $order, $line] = $this->paidOrderWithOneLine(2.0, daysAgo: 1);

        $this->injector?->setCustomer($customer);
        $this->submitReturn($order, $line, '1');

        $return = OrderReturnQuery::create()->filterByOrderId($order->getId())->findOne();
        self::assertNotNull($return);

        $crawler = $this->client->request('GET', $this->url(self::TRACKING_ROUTE, ['returnId' => $return->getId()]));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString((string) $return->getRef(), $crawler->filter('body')->text());
        self::assertStringContainsString('RETURN-LINE', $crawler->filter('body')->text());
    }

    /**
     * An order that is not the customer's answers exactly as an order that does not exist:
     * a 403 would confirm the order is there to be found.
     */
    public function testAnotherCustomersOrderIsNotFound(): void
    {
        [, $order] = $this->paidOrderWithOneLine(2.0, daysAgo: 1);
        $intruder = $this->customer();

        $this->injector?->setCustomer($intruder);
        $this->client->request('GET', $this->url(self::FORM_ROUTE, ['orderId' => $order->getId()]));

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testTheReturnDocumentIsServedOnlyToItsOwner(): void
    {
        $this->skipUnlessThePdfTemplateShipsTheReturnDocument();

        [$customer, $order, $line] = $this->paidOrderWithOneLine(2.0, daysAgo: 1);

        $this->injector?->setCustomer($customer);
        $this->submitReturn($order, $line, '1');

        $return = OrderReturnQuery::create()->filterByOrderId($order->getId())->findOne();
        self::assertNotNull($return);

        // The merchant agrees: the document exists from that point on.
        $acceptedId = OrderReturnStatusQuery::create()->findIdByCode(OrderReturnStatus::CODE_ACCEPTED);
        $return->setStatusId($acceptedId)->save($this->getPropelConnection());

        $documentUrl = $this->url(self::DOCUMENT_ROUTE, ['returnId' => $return->getId()]);

        $this->client->request('GET', $documentUrl);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-type'));

        $this->injector?->setCustomer($this->customer());
        $this->client->request('GET', $documentUrl);
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testNothingIsReachableWhenTheFeatureIsDisabled(): void
    {
        [$customer, $order, $line] = $this->paidOrderWithOneLine(2.0, daysAgo: 1);

        $this->injector?->setCustomer($customer);
        $this->submitReturn($order, $line, '1');

        $return = OrderReturnQuery::create()->filterByOrderId($order->getId())->findOne();
        self::assertNotNull($return);

        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '0');

        foreach ([
            $this->url(self::FORM_ROUTE, ['orderId' => $order->getId()]),
            $this->url(self::TRACKING_ROUTE, ['returnId' => $return->getId()]),
            $this->url(self::DOCUMENT_ROUTE, ['returnId' => $return->getId()]),
        ] as $url) {
            $this->client->request('GET', $url);
            self::assertSame(404, $this->client->getResponse()->getStatusCode(), $url.' must be gone.');
        }

        $crawler = $this->client->request('GET', $this->url('account_order', ['orderId' => $order->getId()]));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringNotContainsString('/return', $crawler->filter('body')->html());
    }

    /**
     * The printable document is a file of the PDF template package, which ships on its own
     * release cycle: without it the front route has nothing to render and answers 404, so
     * this is reported as skipped rather than as a broken ownership guard.
     */
    private function skipUnlessThePdfTemplateShipsTheReturnDocument(): void
    {
        $pdfTemplate = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate();

        if (!file_exists($pdfTemplate->getAbsolutePath().\DIRECTORY_SEPARATOR.'order_return.html.twig')) {
            self::markTestSkipped('The active PDF template ships no return document.');
        }
    }

    /**
     * Fills the real form of the page and posts it, so the CSRF token and the field names
     * are the ones the theme renders rather than ones this test made up.
     */
    private function submitReturn(Order $order, OrderProduct $line, string $quantity): void
    {
        $crawler = $this->client->request('GET', $this->url(self::FORM_ROUTE, ['orderId' => $order->getId()]));

        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'The return form must be reachable.');

        $form = $crawler->filter('form[method="POST"]')->form();
        $form['lines['.$line->getId().'][selected]']->tick();
        $form['lines['.$line->getId().'][quantity]']->setValue($quantity);
        $form['resolution']->select('refund');

        $this->client->submit($form);
    }

    /**
     * A paid order of the given age, carrying one line of `quantity` units.
     *
     * @return array{0: Customer, 1: Order, 2: OrderProduct}
     */
    private function paidOrderWithOneLine(float $quantity, int $daysAgo): array
    {
        $customer = $this->customer();
        $order = $this->factory()->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);

        $order->setCreatedAt(new \DateTime(\sprintf('-%d days', $daysAgo)));
        $order->save($this->getPropelConnection());

        $reference = 'RETURN-LINE-'.$order->getId();

        $orderProduct = new OrderProduct();
        $orderProduct->setOrderId($order->getId());
        $orderProduct->setProductRef($reference);
        $orderProduct->setProductSaleElementsRef($reference.'-PSE');
        $orderProduct->setTitle('A chair to send back');
        $orderProduct->setQuantity($quantity);
        $orderProduct->setPrice('100.000000');
        $orderProduct->setPromoPrice('0.000000');
        $orderProduct->setWasNew(0);
        $orderProduct->setWasInPromo(0);
        $orderProduct->setVirtual(0);
        $orderProduct->save($this->getPropelConnection());

        return [$customer, $order, $orderProduct];
    }

    private function customer(): Customer
    {
        return $this->factory()->customer($this->factory()->customerTitle());
    }

    private function enableReturns(string $windowDays): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');
        ConfigQuery::write(ReturnEligibilityChecker::WINDOW_CONFIG_KEY, $windowDays);
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
