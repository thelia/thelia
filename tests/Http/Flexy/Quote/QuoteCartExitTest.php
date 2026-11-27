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

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\Quote;

/**
 * The way out of the cart of an accepted quote. The buyer who holds it cannot add to it nor change it: the pages of the
 * tunnel offer to give them their own cart back, and so do the refusals of the components that add to the cart. The
 * quote is left as it was, and can be accepted again.
 */
final class QuoteCartExitTest extends QuoteFlexyTestCase
{
    use InteractsWithLiveComponents;

    private const EXIT_FORM = '[data-testid="quote-cart-exit-form"]';

    public function testTheCartStepAndThePaymentStepOfAQuoteCartOfferTheWayBackAsAPostWithTheToken(): void
    {
        $this->acceptedWithAnOrdinaryCartAside();

        foreach (['/checkout/cart', '/checkout/payment'] as $path) {
            $page = $this->get($path);

            self::assertSame(200, $this->statusCode(), $path);
            $form = $page->filter(self::EXIT_FORM);
            self::assertCount(1, $form, $path);
            self::assertSame('post', strtolower((string) $form->attr('method')), $path);
            self::assertSame($this->url('account_quote_cart_leave'), $form->attr('action'), $path);
            self::assertNotSame('', (string) $form->filter('input[name="_token"]')->attr('value'), $path);
            self::assertCount(1, $form->filter('button[type="submit"]'), $path);
        }
    }

    public function testTheWayBackIsStillOfferedWhenTheShopStoppedTakingQuotesSinceTheAcceptance(): void
    {
        $this->acceptedWithAnOrdinaryCartAside();
        $this->setQuoteMode('disabled');

        $page = $this->get('/checkout/cart');

        self::assertSame(200, $this->statusCode());
        self::assertCount(1, $page->filter(self::EXIT_FORM));
    }

    public function testAnOrdinaryCartIsOfferedNoWayOut(): void
    {
        $buyer = $this->customer();
        $this->signInWithCart($buyer, $this->cartOf($buyer, [[$this->product('10', 'Own product'), 1.0, '10']]));

        $page = $this->get('/checkout/cart');

        self::assertSame(200, $this->statusCode());
        self::assertCount(0, $page->filter('[data-testid="quote-cart-exit"]'));
    }

    public function testLeavingGivesTheBuyerTheirOwnCartBackIntactAndTheQuoteCanBeAcceptedAgain(): void
    {
        [$scenario, $ordinary] = $this->acceptedWithAnOrdinaryCartAside();
        $quoteCart = $this->theCartOf($scenario['quote']);
        $before = $this->linesOf($ordinary);
        self::assertSame($ordinary->getId(), $quoteCart->getSetAsideCartId(), 'The cart of the buyer was set aside when they accepted.');

        $this->leave($this->tokenOfTheExit('/checkout/cart'));

        self::assertResponseRedirects('/checkout/cart');
        $cartPage = $this->get('/checkout/cart');
        self::assertStringContainsString('Own product', $cartPage->filter('body')->text(), 'The cart in hand is the one of the buyer.');
        self::assertStringNotContainsString('Product C', $cartPage->filter('body')->text(), 'Nothing of the quote is left in it.');
        self::assertCount(0, $cartPage->filter('[data-testid="quote-cart-exit"]'));
        self::assertSame($before, $this->linesOf($ordinary), 'What the buyer had put in their cart is untouched.');
        self::assertSame('accepted', $this->reread($scenario['quote'])->getState(), 'The quote stays as it was.');
        self::assertSame($quoteCart->getId(), $this->theCartOf($scenario['quote'])->getId(), 'Its cart is kept.');
        self::assertNull($this->theCartOf($scenario['quote'])->getSetAsideCartId());

        // The page of the quote offers the payment again, and the cart it gives is the same.
        $quotePage = $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));
        $resume = $quotePage->filter('[data-testid="quote-resume-form"]');
        self::assertCount(1, $resume);
        $this->client->request('POST', (string) $resume->attr('action'), ['_token' => (string) $resume->filter('input[name="_token"]')->attr('value'), 'revision' => (string) $resume->filter('input[name="revision"]')->attr('value')]);

        self::assertResponseRedirects('/checkout/payment');
        self::assertSame($quoteCart->getId(), $this->theCartOf($scenario['quote'])->getId());
        self::assertSame($ordinary->getId(), $this->theCartOf($scenario['quote'])->getSetAsideCartId(), 'The cart of the buyer is set aside again.');
    }

    public function testLeavingWithNothingSetAsideGivesAnEmptyCart(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);
        $this->signInAs($scenario['buyer']);
        $quoteCart = $this->theCartOf($scenario['quote']);
        self::assertNull($quoteCart->getSetAsideCartId());

        $this->leave($this->tokenOfTheExit('/checkout/cart'));

        self::assertResponseRedirects('/checkout/cart');
        $cartPage = $this->get('/checkout/cart');
        self::assertStringNotContainsString('Product C', $cartPage->filter('body')->text());
        self::assertCount(0, $cartPage->filter('[data-testid="quote-cart-exit"]'));
        self::assertCount(0, $cartPage->filter('.CartItem'), 'An empty cart.');
        self::assertSame(3, CartItemQuery::create()->filterByCartId($quoteCart->getId())->count(), 'The cart of the quote keeps its lines.');
        self::assertSame('accepted', $this->reread($scenario['quote'])->getState());
    }

    public function testAnOrdinaryCartIsLeftAloneByTheWayOut(): void
    {
        $buyer = $this->customer();
        $ordinary = $this->cartOf($buyer, [[$this->product('10', 'Own product'), 2.0, '10']]);
        $token = $this->tokenForTheSession();
        $this->signInWithCart($buyer, $ordinary);
        $before = $this->linesOf($ordinary);

        $this->leave($token);

        self::assertResponseRedirects('/checkout/cart');
        self::assertSame($before, $this->linesOf($ordinary));
        // The session is read as it stands now, not given the cart again by the sign-in of the test.
        $this->signInAs(null);
        $this->signInAs($buyer);
        self::assertStringContainsString('Own product', $this->get('/checkout/cart')->filter('body')->text(), 'The cart in hand is still theirs.');
    }

    public function testTheOrdinaryCartIsBackAfterTheBuyerLeftTheQuoteCartAndSignedOutAndInAgain(): void
    {
        $password = 'a-password-of-a-real-account';
        $buyer = $this->factory->customer($this->factory->customerTitle(), ['password' => $password]);
        $buyer->setEnable(1)->save($this->getPropelConnection());
        $scenario = $this->quotedQuoteReadyToPay($buyer);
        $ordinary = $this->cartOf($buyer, [[$this->product('10', 'Own product'), 2.0, '10']]);
        $token = $this->tokenForTheSession();
        $this->signInWithCart($buyer, $ordinary);
        // The cookie the shop wrote when the cart was created, and which taking up the cart of a quote never changes.
        $this->client->getCookieJar()->set(new Cookie(ConfigQuery::read('cart.cookie_name', 'thelia_cart'), (string) $ordinary->getToken()));
        $this->client->request('POST', $this->url('account_quote_accept', ['quoteId' => $scenario['quote']->getId()]), ['_token' => $token, 'revision' => '2']);
        self::assertSame(302, $this->statusCode());
        $this->signInAs(null);
        $this->signInAs($buyer);
        $this->leave($this->tokenOfTheExit('/checkout/cart'));
        self::assertResponseRedirects('/checkout/cart');

        // A real sign-out and a real sign-in: the session is the one the shop opens, not the one the test hands out.
        $this->signInAs(null);
        $this->client->request('GET', '/customer/logout');
        $form = $this->client->request('GET', '/customer/login')->filter('form[name="thelia_customer_login"]')->form();
        $form['thelia_customer_login[email]'] = (string) $buyer->getEmail();
        $form['thelia_customer_login[password]'] = $password;
        $form['thelia_customer_login[account]'] = '1';
        $this->client->submit($form);
        self::assertStringNotContainsString('/customer/login', (string) $this->client->getResponse()->headers->get('Location'), 'The sign-in went through.');

        $cartPage = $this->get('/checkout/cart');

        self::assertStringContainsString('Own product', $cartPage->filter('body')->text(), 'The buyer finds their own cart.');
        self::assertStringNotContainsString('Product C', $cartPage->filter('body')->text(), 'Nothing of the quote.');
        self::assertCount(0, $cartPage->filter('[data-testid="quote-cart-exit"]'), 'The cart in hand is not the cart of the quote.');
        self::assertSame('accepted', $this->reread($scenario['quote'])->getState(), 'The quote stays accepted.');
        self::assertCount(3, CartItemQuery::create()->filterByCartId($this->theCartOf($scenario['quote'])->getId())->find(), 'And its cart keeps its lines.');
    }

    public function testTheWayOutIsAPostWithTheTokenOfTheAccountForASignedInBuyer(): void
    {
        [$scenario, $ordinary] = $this->acceptedWithAnOrdinaryCartAside();
        $quoteCart = $this->theCartOf($scenario['quote']);

        foreach ([null, '', 'forged'] as $token) {
            $this->client->request('POST', $this->url('account_quote_cart_leave'), null === $token ? [] : ['_token' => $token]);

            self::assertSame(403, $this->statusCode(), (string) $token);
        }

        $this->get($this->url('account_quote_cart_leave'));
        self::assertSame(405, $this->statusCode(), 'Not a link.');

        self::assertCount(1, $this->get('/checkout/cart')->filter('[data-testid="quote-cart-exit"]'), 'The cart in hand is still the cart of the quote.');
        self::assertSame($quoteCart->getId(), $this->theCartOf($scenario['quote'])->getId());
        self::assertSame($ordinary->getId(), $quoteCart->getSetAsideCartId());
    }

    public function testAVisitorIsSentToSignIn(): void
    {
        $this->client->request('POST', $this->url('account_quote_cart_leave'), ['_token' => 'x']);

        self::assertSame(302, $this->statusCode());
        self::assertStringContainsString($this->url('customer_login'), (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testAddingFromAProductSheetWithTheCartOfAQuoteInHandSaysSoAndOffersTheWayBack(): void
    {
        $product = $this->product('10', 'Product X');
        // The sheet is mounted before the buyer accepts: mounting runs in this process, and the requests that follow are the client's.
        $props = $this->propsOfTheSheetOf($product);
        [$scenario, $ordinary] = $this->acceptedWithAnOrdinaryCartAside();
        $quoteLines = $this->linesOf($this->theCartOf($scenario['quote']));

        $html = $this->addFromTheSheet($props);

        $page = new Crawler($html);
        self::assertSame(200, $this->statusCode(), 'The sheet answers, it is not a server error.');
        self::assertStringContainsString('the cart in hand is the cart of an accepted quote', $page->filter('[data-testid="quote-cart-exit"]')->text());
        self::assertCount(1, $page->filter('[data-testid="quote-cart-exit"] '.self::EXIT_FORM.' button[type="submit"]'));
        self::assertCount(0, $page->filterXPath('//*[@data-testid="quote-cart-exit"]/ancestor::form'), 'Drawn out of the cart form of the sheet: a form does not hold another.');
        self::assertSame($quoteLines, $this->linesOf($this->theCartOf($scenario['quote'])), 'The cart of the quote is not touched.');
        self::assertSame(0, CartItemQuery::create()->filterByProductId($product->getId())->count(), 'The product went into no cart.');

        // The way out the sheet offers leads back to the cart the buyer had, which the add did not reach.
        $form = $page->filter(self::EXIT_FORM);
        $this->client->request('POST', (string) $form->attr('action'), ['_token' => (string) $form->filter('input[name="_token"]')->attr('value')]);
        self::assertResponseRedirects('/checkout/cart');
        self::assertStringContainsString('Own product', $this->get('/checkout/cart')->filter('body')->text());
        self::assertSame(1, CartItemQuery::create()->filterByCartId($ordinary->getId())->count(), 'The cart of the buyer holds what it held.');
    }

    public function testAddingFromTheQuickOrderTableWithTheCartOfAQuoteInHandSaysSoAndOffersTheWayBack(): void
    {
        [$scenario] = $this->acceptedWithAnOrdinaryCartAside();
        $quoteLines = $this->linesOf($this->theCartOf($scenario['quote']));
        $product = $this->product('10', 'Product Q');
        $reference = (string) $product->getProductSaleElementss()->getFirst()->getRef();

        $table = $this->createLiveComponent('Organisms:QuickOrderTable:Base', client: $this->client);
        $table->set('rows', [['key' => bin2hex(random_bytes(4)), 'reference' => $reference, 'quantity' => 2, 'productSaleElementsId' => null]]);
        $table->call('check');
        $table->call('addToCart');

        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'The table answers, it is not a server error.');
        $page = $table->render()->crawler();
        self::assertCount(1, $page->filter('.QuickOrderTable-messages [data-testid="quote-cart-exit"] '.self::EXIT_FORM.' button[type="submit"]'));
        self::assertStringContainsString('None of these lines could be added', $page->filter('.QuickOrderTable-messages')->text());
        self::assertSame($quoteLines, $this->linesOf($this->theCartOf($scenario['quote'])), 'The cart of the quote is not touched.');
        self::assertStringContainsString($reference, $page->filter('.QuickOrderTable-table')->html(), 'The row stays for the buyer to add to their own cart.');
    }

    /**
     * The quote accepted by a buyer who had a cart of their own: it is in the session of the client now, the cart of
     * the buyer set aside on it.
     *
     * @return array{0: array{buyer: Customer, quote: Quote, a: Product, b: Product, c: Product}, 1: Cart}
     */
    private function acceptedWithAnOrdinaryCartAside(): array
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $ordinary = $this->cartOf($scenario['buyer'], [[$this->product('10', 'Own product'), 2.0, '10']]);
        $token = $this->tokenForTheSession();
        $this->signInWithCart($scenario['buyer'], $ordinary);
        $this->client->request('POST', $this->url('account_quote_accept', ['quoteId' => $scenario['quote']->getId()]), ['_token' => $token, 'revision' => '2']);
        self::assertSame(302, $this->statusCode());

        // From here the session carries the cart of the quote: it is not the cart of the buyer that is put in it.
        $this->signInAs(null);
        $this->signInAs($scenario['buyer']);

        return [$scenario, $ordinary];
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

    private function tokenOfTheExit(string $path): string
    {
        return (string) $this->get($path)->filter(self::EXIT_FORM.' input[name="_token"]')->attr('value');
    }

    private function leave(string $token): void
    {
        $this->client->request('POST', $this->url('account_quote_cart_leave'), ['_token' => $token]);
    }

    /**
     * The state of the sheet of the product as the page prints it, which the browser sends back with each action.
     *
     * @return array<string, mixed>
     */
    private function propsOfTheSheetOf(Product $product): array
    {
        $sheet = $this->createLiveComponent('Layouts:ProductDetails:Base', [
            'product' => ['id' => (int) $product->getId(), 'i18ns' => ['title' => 'Product X']],
            'title' => 'Product X',
        ], $this->client);

        // The first render mounts the component in this process, and mount() reads the API: it needs a request on the stack,
        // taken off again once the component answers so that the later requests start from a clean stack.
        $requestStack = $this->getService(RequestStack::class);
        $request = TheliaRequest::create('/', 'GET', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $request->setSession($this->getService('session.factory')->createSession());
        $requestStack->push($request);

        try {
            $props = $sheet->render()->crawler()->filter('[data-live-props-value]')->attr('data-live-props-value');
        } finally {
            $requestStack->pop();
        }

        return json_decode((string) $props, true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * What the sheet answers when the buyer adds the product, as the browser sends it. The form of the sheet is protected by
     * a stateless token, valid for a request that comes from the page itself.
     *
     * @param array<string, mixed> $props
     */
    private function addFromTheSheet(array $props): string
    {
        $this->client->request(
            'POST',
            $this->url('ux_live_component', ['_live_component' => 'Layouts:ProductDetails:Base', '_live_action' => 'save']),
            ['data' => json_encode(['props' => $props, 'args' => []], \JSON_THROW_ON_ERROR)],
            [],
            ['HTTP_ORIGIN' => 'http://localhost'],
        );

        return (string) $this->client->getResponse()->getContent();
    }

    private function theCartOf(Quote $quote): Cart
    {
        \Thelia\Model\Map\CartTableMap::clearInstancePool();

        return CartQuery::create()->filterByQuoteId($quote->getId())->findOne() ?? self::fail('The quote has no cart.');
    }

    /**
     * @return list<array{float, string}>
     */
    private function linesOf(Cart $cart): array
    {
        \Thelia\Model\Map\CartItemTableMap::clearInstancePool();
        $lines = [];

        foreach (CartItemQuery::create()->filterByCartId($cart->getId())->orderById()->find() as $line) {
            $lines[] = [(float) $line->getQuantity(), (string) $line->getPrice()];
        }

        return $lines;
    }
}
