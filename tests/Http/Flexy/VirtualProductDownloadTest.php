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
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * A customer who bought a virtual product must be able to reach their file from the
 * order page, and nobody else must be able to reach it at all.
 *
 * The link is shown on a line that carries a document, on an order that is paid for.
 * The route enforces the same two conditions plus ownership, so a link that is not
 * displayed is also a url that answers nothing.
 *
 * These tests drive the routes of the installed Flexy theme; a theme older than the
 * fix has no download route and is reported as skipped rather than failed, because
 * the theme ships as its own package on its own release cycle.
 */
final class VirtualProductDownloadTest extends WebIntegrationTestCase
{
    private const DOWNLOAD_ROUTE = 'account_order_download';

    private CustomerSessionInjector $injector;

    protected function setUp(): void
    {
        parent::setUp();

        if (null === $this->getService(RouterInterface::class)->getRouteCollection()->get(self::DOWNLOAD_ROUTE)) {
            self::markTestSkipped('The installed front-office theme has no virtual product download route.');
        }

        $this->injector = new CustomerSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        $this->injector->clear();

        parent::tearDown();
    }

    public function testPaidOrderOffersTheFileOfItsDocumentedVirtualLine(): void
    {
        $customer = $this->customer();
        $line = $this->virtualLine($this->order($customer, OrderStatus::CODE_PAID), 'invoice-manual.pdf');

        $this->loginAs($customer);
        $this->assertPageRenders('/account/order/'.$line->getOrderId());

        self::assertStringContainsString(
            $this->downloadUrl($line),
            (string) $this->client->getResponse()->getContent(),
            'A paid order must offer the file of its documented virtual line.',
        );
    }

    public function testUnpaidOrderOffersNoFile(): void
    {
        $customer = $this->customer();
        $line = $this->virtualLine($this->order($customer, OrderStatus::CODE_NOT_PAID), 'invoice-manual.pdf');

        $this->loginAs($customer);
        $this->assertPageRenders('/account/order/'.$line->getOrderId());

        self::assertStringNotContainsString(
            $this->downloadUrl($line),
            (string) $this->client->getResponse()->getContent(),
            'An order that is not paid for must not offer a link the route answers 404 to.',
        );
    }

    public function testDownloadIsRefusedOnAnotherCustomersLine(): void
    {
        $line = $this->virtualLine($this->order($this->customer(), OrderStatus::CODE_PAID), 'invoice-manual.pdf');

        $this->loginAs($this->customer());
        $this->client->request('GET', $this->downloadUrl($line));

        // 404 rather than 403: a customer must not learn that the order line exists.
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testDownloadIsRefusedWhileTheOrderIsNotPaid(): void
    {
        $customer = $this->customer();
        $line = $this->virtualLine($this->order($customer, OrderStatus::CODE_NOT_PAID), 'invoice-manual.pdf');

        $this->loginAs($customer);
        $this->client->request('GET', $this->downloadUrl($line));

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testDownloadIsRefusedOnAVirtualLineWithoutADocument(): void
    {
        $customer = $this->customer();
        $line = $this->virtualLine($this->order($customer, OrderStatus::CODE_PAID), null);

        $this->loginAs($customer);
        $this->client->request('GET', $this->downloadUrl($line));

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    private function loginAs(Customer $customer): void
    {
        $this->injector->setCustomer($customer);
    }

    private function customer(): Customer
    {
        return $this->factory()->customer($this->factory()->customerTitle());
    }

    private function order(Customer $customer, string $statusCode): Order
    {
        return $this->factory()->order($customer, ['statusCode' => $statusCode]);
    }

    /**
     * The factory stops at the order itself, so the line is built here. It snapshots
     * its own reference and title, which is all the order page reads from it.
     */
    private function virtualLine(Order $order, ?string $document): OrderProduct
    {
        $reference = 'VIRTUAL-'.$order->getId();

        $orderProduct = new OrderProduct();
        $orderProduct->setOrderId($order->getId());
        $orderProduct->setProductRef($reference);
        $orderProduct->setProductSaleElementsRef($reference.'-PSE');
        $orderProduct->setTitle('A file to download');
        $orderProduct->setQuantity(1.0);
        $orderProduct->setPrice('10.000000');
        $orderProduct->setPromoPrice('0.000000');
        $orderProduct->setWasNew(0);
        $orderProduct->setWasInPromo(0);
        $orderProduct->setVirtual(1);
        $orderProduct->setVirtualDocument($document);
        $orderProduct->save($this->getPropelConnection());

        return $orderProduct;
    }

    private function downloadUrl(OrderProduct $orderProduct): string
    {
        return $this->getService(RouterInterface::class)->generate(
            self::DOWNLOAD_ROUTE,
            ['orderProductId' => $orderProduct->getId()],
        );
    }

    /**
     * Built without createFixtureFactory(): that helper pushes a synthetic request when
     * the stack is empty, and it would then be the "main" request of the calls below —
     * the one the session, and therefore the logged-in customer, is read from.
     */
    private function factory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }
}
