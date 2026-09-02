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
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * The order page of the Flexy theme offers a quote for an order whose status says
 * "quotation", and the route behind that link renders a `quotation` document of the
 * active PDF template. The default PDF template ships an invoice and a delivery slip
 * only, so on a shop that has not added one the route has nothing to render — and a
 * customer walking to that url must be told the page is not there, not handed a
 * server error.
 *
 * The route belongs to the theme, which ships as its own package on its own release
 * cycle: a theme older than the guard is reported as skipped rather than failed.
 */
final class AccountQuotationPdfTest extends WebIntegrationTestCase
{
    private const QUOTATION_ROUTE = 'account_order_pdf_quotation';

    private const GUARDED_CONTROLLER = 'FlexyBundle\Controller\AccountOrderController';

    private ?CustomerSessionInjector $injector = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (null === $this->getService(RouterInterface::class)->getRouteCollection()->get(self::QUOTATION_ROUTE)) {
            self::markTestSkipped('The installed front-office theme has no quotation PDF route.');
        }

        if (!method_exists(self::GUARDED_CONTROLLER, 'pdfDocumentExists')) {
            self::markTestSkipped('The installed front-office theme predates the quotation document guard.');
        }

        $this->injector = new CustomerSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        $this->injector?->clear();

        parent::tearDown();
    }

    public function testAQuoteTheActivePdfTemplateHasNoDocumentForIsNotFound(): void
    {
        $customer = $this->customer();
        $order = $this->factory()->order($customer);

        $this->injector?->setCustomer($customer);
        $this->client->request('GET', $this->quotationUrl($order));

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    private function customer(): Customer
    {
        return $this->factory()->customer($this->factory()->customerTitle());
    }

    private function quotationUrl(Order $order): string
    {
        return $this->getService(RouterInterface::class)->generate(
            self::QUOTATION_ROUTE,
            ['orderId' => $order->getId()],
        );
    }

    /**
     * Built without createFixtureFactory(): that helper pushes a synthetic request when
     * the stack is empty, and it would then be the "main" request of the call below —
     * the one the session, and therefore the logged-in customer, is read from.
     */
    private function factory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }
}
