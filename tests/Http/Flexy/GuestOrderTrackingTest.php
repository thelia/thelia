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

use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;

/**
 * The tracking link is the whole of the authentication for an order placed without an
 * account: it opens exactly one order, and everything else answers the same 404 —
 * a token this shop never issued, one that names another order, and one that has run out.
 */
final class GuestOrderTrackingTest extends GuestCheckoutTestCase
{
    /** @var list<string> */
    private array $documentsWritten = [];

    protected function tearDown(): void
    {
        foreach ($this->documentsWritten as $document) {
            if (is_file($document)) {
                unlink($document);
            }
        }

        $this->documentsWritten = [];

        parent::tearDown();
    }

    public function testAValidLinkShowsTheOrderItNames(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $order = $this->guestOrder();

        $crawler = $this->client->request('GET', $this->trackingPathFor($order));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            (string) $order->getRef(),
            $crawler->text(),
            'The page must show the order the link names.',
        );
    }

    public function testTheShippingIsNotReadAsAnIncludedTax(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        // No product line, only shipping: the order carries no tax at all, so the
        // "Taxes of ... included" line has nothing to say. Before the Summary fix it
        // showed the shipping amount as a tax.
        $order = $this->guestOrder(overrides: ['postage' => 5]);

        $crawler = $this->client->request('GET', $this->trackingPathFor($order));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringNotContainsString(
            'Taxes of',
            $crawler->text(),
            'An order whose only amount is the shipping holds no tax to advertise.',
        );
    }

    public function testATamperedLinkIsRefused(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $order = $this->guestOrder();
        $token = $this->tokenFor($order);

        // One character of the signature, which is all it takes.
        $tampered = substr($token, 0, -1).('a' === substr($token, -1) ? 'b' : 'a');

        $this->client->request('GET', '/order/track/'.$tampered);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testALinkSignedForAnotherOrderIsRefused(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $guest = $this->guest();
        $order = $this->guestOrder($guest);
        $otherOrder = $this->guestOrder($guest);

        // The signature of one order, presented under the number of another.
        [, $expiresAt, $signature] = explode('.', $this->tokenFor($order));

        $this->client->request(
            'GET',
            \sprintf('/order/track/%d.%s.%s', $otherOrder->getId(), $expiresAt, $signature),
        );

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testAnExpiredLinkIsRefused(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $order = $this->guestOrder();
        $token = $this->getService(GuestOrderAccessService::class)->createToken($order, -1);

        $this->client->request('GET', '/order/track/'.$token);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testAnOrderNumberThatNamesNothingAnswersLikeAnyOtherRefusal(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $this->client->request('GET', '/order/track/999999999.9999999999.'.str_repeat('a', 64));

        self::assertSame(
            404,
            $this->client->getResponse()->getStatusCode(),
            'An id that names no order must not be told apart from a bad signature.',
        );
    }

    /**
     * The invoice hangs off the same token, never off the account routes: those resolve
     * the order through the signed-in customer, which a guest is not.
     */
    public function testTheInvoiceOfAPaidOrderIsServedBehindTheSameToken(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $order = $this->guestOrder(statusCode: OrderStatus::CODE_PAID);

        $this->client->request('GET', '/order/track/'.$this->tokenFor($order).'/invoice');

        $response = $this->client->getResponse();

        self::assertSame(
            200,
            $response->getStatusCode(),
            'A paid guest order must have its invoice reachable from its own link.',
        );
        self::assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('Content-Type'),
            'What comes back has to be the invoice itself, not a page that merely answered 200.',
        );
    }

    public function testTheInvoiceIsRefusedToATamperedLink(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $order = $this->guestOrder(statusCode: OrderStatus::CODE_PAID);
        $token = $this->tokenFor($order);
        $tampered = substr($token, 0, -1).('a' === substr($token, -1) ? 'b' : 'a');

        $this->client->request('GET', '/order/track/'.$tampered.'/invoice');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    /**
     * The file bought with a virtual product hangs off the tracking link, not off the
     * account routes: the page used to offer the account one, which resolves the order
     * through a signed-in customer and therefore answers a guest nothing at all.
     */
    public function testTheFileOfAPaidOrderIsServedBehindTheSameToken(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $order = $this->guestOrder(statusCode: OrderStatus::CODE_PAID);
        $line = $this->virtualLineOf($order, $this->aDocumentTheShopHolds());

        $this->client->request(
            'GET',
            \sprintf('/order/track/%s/download/%d', $this->tokenFor($order), $line->getId()),
        );

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'A paid guest order must serve the file of its documented virtual line.',
        );
    }

    public function testTheFileIsRefusedToATamperedLink(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $order = $this->guestOrder(statusCode: OrderStatus::CODE_PAID);
        $line = $this->virtualLineOf($order, $this->aDocumentTheShopHolds());
        $token = $this->tokenFor($order);
        $tampered = substr($token, 0, -1).('a' === substr($token, -1) ? 'b' : 'a');

        $this->client->request('GET', \sprintf('/order/track/%s/download/%d', $tampered, $line->getId()));

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testTheFileOfAnotherOrderIsRefusedToThisOnesToken(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $order = $this->guestOrder(statusCode: OrderStatus::CODE_PAID);
        $someoneElsesOrder = $this->guestOrder(statusCode: OrderStatus::CODE_PAID);
        $theirLine = $this->virtualLineOf($someoneElsesOrder, $this->aDocumentTheShopHolds());

        $this->client->request(
            'GET',
            \sprintf('/order/track/%s/download/%d', $this->tokenFor($order), $theirLine->getId()),
        );

        // 404 rather than 403: the token must not tell whether the other line exists.
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testTheFileIsRefusedWhileTheOrderIsNotPaid(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $order = $this->guestOrder();
        $line = $this->virtualLineOf($order, $this->aDocumentTheShopHolds());

        $this->client->request(
            'GET',
            \sprintf('/order/track/%s/download/%d', $this->tokenFor($order), $line->getId()),
        );

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testTheTrackingPageOffersTheFileOnItsOwnRouteAndNotTheAccountOne(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $order = $this->guestOrder(statusCode: OrderStatus::CODE_PAID);
        $line = $this->virtualLineOf($order, $this->aDocumentTheShopHolds());
        $token = $this->tokenFor($order);

        $crawler = $this->client->request('GET', '/order/track/'.$token);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertGreaterThan(
            0,
            $crawler->filter(\sprintf('a[href="/order/track/%s/download/%d"]', $token, $line->getId()))->count(),
            'The download button must lead to the route this page is authenticated by.',
        );
        self::assertCount(
            0,
            $crawler->filter('a[href^="/account/order/download/"]'),
            'The account route is guarded by a login a guest does not have.',
        );
    }

    /**
     * A virtual line of that order, with a document attached to it.
     *
     * The factory stops at the order itself, so the line is built here. It snapshots its
     * own reference and title, which is all the order page reads from it.
     */
    private function virtualLineOf(Order $order, string $document): OrderProduct
    {
        $reference = 'GUEST-VIRTUAL-'.$order->getId();

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

    /**
     * A document in the shop's own library, removed again in tearDown.
     *
     * The module that serves virtual products reads the file off the disk and refuses an
     * order line naming one that is not there, so a 200 can only be asserted against a
     * file that exists. Written under a name of this test's own so nothing else can be
     * touched by the cleanup.
     */
    private function aDocumentTheShopHolds(): string
    {
        $directory = THELIA_ROOT.'local'.DS.'media'.DS.'documents'.DS.'product';

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            self::markTestSkipped('The shop has no document library to put a virtual product file in.');
        }

        $name = 'guest-order-tracking-test.txt';
        file_put_contents($directory.DS.$name, 'the file the buyer paid for');

        $this->documentsWritten[] = $directory.DS.$name;

        return $name;
    }

    private function trackingPathFor(Order $order): string
    {
        return '/order/track/'.$this->tokenFor($order);
    }

    protected function tokenFor(Order $order): string
    {
        return $this->getService(GuestOrderAccessService::class)->createToken($order);
    }

    protected function guest(): Customer
    {
        $fixtures = $this->fixtures();

        return $fixtures->guestCustomer($fixtures->customerTitle());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function guestOrder(?Customer $guest = null, string $statusCode = OrderStatus::CODE_NOT_PAID, array $overrides = []): Order
    {
        return $this->fixtures()->order($guest ?? $this->guest(), $overrides + ['statusCode' => $statusCode]);
    }
}
