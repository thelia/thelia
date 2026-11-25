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

namespace Thelia\Tests\Http\Flexy\Quote;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;
use Thelia\Model\Quote;

/**
 * The cart of an accepted quote in the tunnel: the buyer reads the prices of the quote, the discount and the shipping the
 * quote fixed, and the total it says; and nothing in it can be changed by the buyer.
 */
final class QuoteTunnelTest extends QuoteFlexyTestCase
{
    use InteractsWithLiveComponents;

    public function testThePaymentStepShowsTheTotalOfTheQuote(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);

        $page = $this->get('/checkout/payment');

        self::assertSame(200, $this->statusCode());
        $summary = $this->summaryOf($page);
        self::assertStringContainsString('€1,632.00', $summary, 'The lines at the prices of the quote: (12 x 90 + 4 x 45 + 5 x 20) with taxes.');
        self::assertStringContainsString('€18.00', $summary, 'The shipping the quote fixed, with its tax.');
        self::assertStringContainsString('€30.00', $summary, 'The discount of the quote.');
        self::assertMatchesRegularExpression('/Total\s*€1,620\.00/', $summary);
    }

    public function testTheCartStepShowsTheNegotiatedPricesAndNoControlOnTheQuantities(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);

        $page = $this->get('/checkout/cart');

        self::assertSame(200, $this->statusCode());
        $lines = $page->filter('.CartItem');
        self::assertCount(3, $lines);
        $prices = $lines->each(static fn (Crawler $line): string => self::flat($line->filter('.CartItem-price')->text()));
        self::assertEqualsCanonicalizing(['€108.00 incl. tax', '€54.00 incl. tax', '€24.00 incl. tax'], $prices, 'The negotiated prices of the quote, with taxes, not the catalogue ones.');
        self::assertCount(0, $page->filter('.CartItem-quantity'), 'No stepper.');
        self::assertCount(0, $page->filter('.CartItem input'), 'No field.');
        self::assertCount(0, $page->filter('.CartItem-actions button'), 'No way to delete a line.');
        self::assertStringContainsString('Quantity: 12', $lines->eq(0)->text(), 'The quantity of the quote is read.');
        self::assertStringContainsString('€1,620.00', $this->summaryOf($page));
    }

    public function testGoingBackToTheCartStepKeepsTheCarrierAndTheAddressesOfTheQuote(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);
        $before = $this->theCartOf($scenario['quote']);
        $carrier = $before->getDeliveryModuleId();
        $addresses = [$before->getAddressDeliveryId(), $before->getAddressInvoiceId()];
        self::assertNotNull($carrier);

        $this->get('/checkout/cart');
        $this->get('/checkout/payment');

        self::assertSame(200, $this->statusCode(), 'The payment is still reachable.');
        $after = $this->theCartOf($scenario['quote']);
        self::assertSame($carrier, $after->getDeliveryModuleId());
        self::assertSame($addresses, [$after->getAddressDeliveryId(), $after->getAddressInvoiceId()]);
    }

    public function testTheQuantitiesOfTheCartOfAQuoteAreNotChangedByTheCartComponent(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);
        $cart = $this->theCartOf($scenario['quote']);
        $before = $this->linesOf($cart->getId());
        $this->get('/checkout/cart');
        $this->mountWithTheCartOf($scenario['quote']);

        $component = $this->createLiveComponent('Organisms:Cart:Base', [], $this->client);

        foreach (['plus', 'minus', 'remove'] as $action) {
            $component->call($action, ['index' => 0]);
            self::assertSame($before, $this->linesOf($cart->getId()), $action);
        }

        $component->call('onQuantityChanged', ['index' => 0, 'baseQuantity' => 12.0]);
        self::assertSame($before, $this->linesOf($cart->getId()), 'onQuantityChanged');
    }

    public function testALineTheBrowserSaysIsNotFrozenIsStillRefusedByTheCoreAndTheBuyerIsToldNotServedAnError(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);
        $cart = $this->theCartOf($scenario['quote']);
        $before = $this->linesOf($cart->getId());
        $this->get('/checkout/cart');
        $this->mountWithTheCartOf($scenario['quote']);

        $component = $this->createLiveComponent('Organisms:Cart:Base', [], $this->client);
        $component->refresh();

        foreach (['plus', 'remove'] as $action) {
            // `items` is the browser's to write: it is sent with the action, as a hand-made request would send it.
            $props = json_decode((string) $component->render()->crawler()->filter('[data-live-props-value]')->attr('data-live-props-value'), true, flags: \JSON_THROW_ON_ERROR);
            $items = array_map(static fn (array $item): array => ['isFrozen' => false] + $item, $props['items']);
            $this->client->request('POST', $this->url('ux_live_component', ['_live_component' => 'Organisms:Cart:Base', '_live_action' => $action]), [
                'data' => json_encode(['props' => $props, 'updated' => ['items' => $items], 'args' => ['index' => 0]], \JSON_THROW_ON_ERROR),
            ]);

            self::assertSame(200, $this->statusCode(), $action);
            self::assertStringContainsString('The lines of a quote cannot be changed', (string) $this->client->getResponse()->getContent(), $action);
            self::assertSame($before, $this->linesOf($cart->getId()), $action);
        }
    }

    public function testAPromoCodeOnTheCartOfAQuoteIsAnsweredWithTheSentenceAndNotAnError(): void
    {
        $this->factory->coupon(['code' => 'SAVE5']);
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);
        $this->get('/checkout/payment');
        // The token of this form is the stateless one: the request has to come from the shop itself.
        $this->mountWithTheCartOf($scenario['quote']);

        // The action is run in process: the form of the live request cannot be given its token from a test client.
        $component = $this->getService('ux.twig_component.component_factory')->get('Forms:PromoCode:Base');
        $component->formValues = ['coupon-code' => 'SAVE5', '_token' => 'csrf-token'];
        $component->save();

        self::assertSame('A promo code cannot be used on a quote.', $component->error);
        self::assertSame('30.000000', (string) $this->theCartOf($scenario['quote'])->getDiscount(), 'The discount of the quote is left as it was.');
    }

    public function testTheTunnelOfTheCartOfAQuoteDoesNotOfferAPromoCodeField(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);

        foreach (['/checkout/cart', '/checkout/payment'] as $path) {
            $page = $this->get($path);

            self::assertSame(200, $this->statusCode(), $path);
            self::assertGreaterThan(0, $page->filter('.Summary')->count(), $path.': the summary is still there.');
            self::assertCount(0, $page->filter('.PromoCode'), $path);
        }
    }

    public function testTheCartOfAnOrdinaryOrderStillOffersAPromoCodeField(): void
    {
        $ordinary = $this->customer();
        $this->signInWithCart($ordinary, $this->cartOf($ordinary, [[$this->product('10', 'Product S'), 1.0, '10']]));

        $page = $this->get('/checkout/cart');

        self::assertGreaterThan(0, $page->filter('.Summary')->count());
        self::assertCount(1, $page->filter('.PromoCode'));
    }

    /**
     * @param array{buyer: Customer, quote: Quote} $scenario
     */
    private function accept(array $scenario): void
    {
        $token = $this->tokenForTheSession();
        $this->signInAs($scenario['buyer']);
        $this->client->request('POST', $this->url('account_quote_accept', ['quoteId' => $scenario['quote']->getId()]), ['_token' => $token, 'revision' => '2']);
        self::assertSame(302, $this->statusCode());
    }

    private function summaryOf(Crawler $page): string
    {
        return self::flat($page->filter('.Summary')->first()->text());
    }

    /**
     * A text on one line, the non-breaking spaces of the number formats being spaces.
     */
    private static function flat(string $text): string
    {
        return trim(preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text) ?? '');
    }

    /**
     * What the in-process mount of a component needs: a request in progress whose session holds the cart. The
     * calls that follow are requests of the client, which carry their own.
     */
    private function mountWithTheCartOf(Quote $quote): void
    {
        $session = $this->getService(Session::class);
        $session->set(Session::SESSION_CART_ID_NAME, $this->theCartOf($quote)->getId());
        $request = Request::create('/checkout/cart');
        $request->headers->set('Sec-Fetch-Site', 'same-origin');
        $request->setSession($session);
        $this->getService(RequestStack::class)->push($request);
    }

    private function theCartOf(Quote $quote): \Thelia\Model\Cart
    {
        \Thelia\Model\Map\CartTableMap::clearInstancePool();

        return CartQuery::create()->filterByQuoteId($quote->getId())->findOne() ?? self::fail('The quote has no cart.');
    }

    /**
     * @return list<array{float, string}>
     */
    private function linesOf(int $cartId): array
    {
        \Thelia\Model\Map\CartItemTableMap::clearInstancePool();
        $lines = [];

        foreach (CartItemQuery::create()->filterByCartId($cartId)->orderById()->find() as $line) {
            $lines[] = [(float) $line->getQuantity(), (string) $line->getPrice()];
        }

        return $lines;
    }
}
