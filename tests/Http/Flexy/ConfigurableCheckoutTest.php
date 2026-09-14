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

use FlexyBundle\Service\CheckoutStepRouteResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Component\DomCrawler\Crawler;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Enum\CheckoutDisplayMode;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Domain\Checkout\Exception\CheckoutException;
use Thelia\Domain\Checkout\Service\CheckoutProgressionService;
use Thelia\Domain\Checkout\Service\CheckoutStepTitleResolver;
use Thelia\Domain\Checkout\Service\CheckoutTunnelShape;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepProviderInterface;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\CheckoutStep;
use Thelia\Model\CheckoutStepQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Consent;
use Thelia\Model\CountryArea;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\CheckoutStepTableMap;
use Thelia\Model\Map\ConsentTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;

/**
 * The checkout the shop configured, seen from a browser.
 *
 * Two things are being checked here, and they are not the same thing. The first is that
 * the theme shows the tunnel the `checkout_step` table describes: a step turned off, and
 * one left out for a cart with nothing to ship, is gone from the progression bar and its
 * url no longer serves a screen. The second is that none of that loosens anything — the
 * order is still refused at the placement by the core's own checks, which know nothing
 * about how many screens the buyer was walked through.
 *
 * Both layouts are exercised the same way: the several-screen checkout a shop has by
 * default, and the one-page form, where the same step components are stacked on the cart
 * page and open one after the other as the cart earns them.
 */
final class ConfigurableCheckoutTest extends GuestCheckoutTestCase
{
    /**
     * The theme ships as its own package on its own release cycle, and the core is
     * released with whichever version of it is installed: one that predates the
     * configurable checkout has no such class.
     */
    private const ROUTE_RESOLVER = 'FlexyBundle\Service\CheckoutStepRouteResolver';

    private const ONE_PAGE_COMPONENT = 'Organisms:CheckoutOnePage:Base';

    private const PAYMENT_STEP_COMPONENT = 'Organisms:Payment:Base';

    /**
     * The sections of the tunnel, and nothing else: the summary in the sidebar opens its
     * promotion code on an accordion of its own.
     */
    private const SECTION_SELECTOR = '#checkout-step > [data-slot="accordion-item"]';

    /**
     * The code of a step standing in for the one a module declares: one this theme has
     * no screen for, and no route of its own.
     */
    private const MODULE_STEP_CODE = 'test_gift_message';

    private const QUOTED_POSTAGE = '12.000000';

    private const QUOTED_POSTAGE_TAX = '2.000000';

    private ?string $previousDisplayMode = null;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    private bool $deliveryStepWasTurnedOff = false;

    /**
     * Skipped before anything is booted, so that nothing below asks for a container the
     * skip means was never wanted.
     */
    protected function setUp(): void
    {
        if (!class_exists(self::ROUTE_RESOLVER)) {
            self::markTestSkipped('The installed front-office theme predates the configurable checkout.');
        }

        parent::setUp();

        $this->skipUnlessTheThemeHasTheIdentificationPage();

        $this->previousDisplayMode = ConfigQuery::getCheckoutDisplayMode();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        $this->forgetWhatTheKernelIsHoldingOnTo();
    }

    /**
     * Survives the skip: everything below is guarded on the client the skip never
     * created, and every setting is written back rather than rolled back — the static
     * caches behind them outlive the transaction.
     */
    protected function tearDown(): void
    {
        if (!isset($this->client)) {
            parent::tearDown();

            return;
        }

        foreach ($this->registeredListeners as [$eventName, $listener]) {
            static::getContainer()->get('event_dispatcher')->removeListener($eventName, $listener);
        }

        $this->registeredListeners = [];

        if ($this->deliveryStepWasTurnedOff) {
            $this->setTheDeliveryStepActive(true);
            $this->deliveryStepWasTurnedOff = false;
        }

        if (null !== $this->previousDisplayMode) {
            ConfigQuery::write('checkout_display_mode', $this->previousDisplayMode);
            $this->previousDisplayMode = null;
        }

        $this->forgetWhatTheKernelIsHoldingOnTo();

        parent::tearDown();
    }

    public function testTheSeveralScreenCheckoutWalksACartAllTheWayToAnOrder(): void
    {
        $cart = $this->openACheckoutReadyCart();

        $crawler = $this->requestAPageThatMustRender('/checkout/delivery');

        self::assertSame(
            ['Your cart', 'Delivery', 'Payment', 'Confirmation'],
            $this->stepsNamedBy($crawler),
            'The bar must name the four steps the shop ships with, in their configured order.',
        );

        $this->tickTheTermsAndConditions($this->requestAPageThatMustRender('/checkout/payment'));

        $this->client->request('GET', '/checkout/pay');

        self::assertSame(
            1,
            OrderQuery::create()->filterByCartId($cart->getId())->count(),
            'A cart walked through every screen of the tunnel must end up as an order.',
        );
    }

    public function testTheOnePageCheckoutWalksACartAllTheWayToAnOrder(): void
    {
        $this->layOutTheCheckout(CheckoutDisplayMode::OnePage);

        $cart = $this->openACheckoutReadyCart();

        $crawler = $this->requestAPageThatMustRender('/checkout/cart');

        self::assertCount(
            0,
            $crawler->filter('.CheckoutSteps'),
            'The one-page layout carries no progression bar: the stacked sections are the tunnel.',
        );
        self::assertSame(
            ['cart', 'delivery', 'payment'],
            $this->sectionsOf($crawler),
            'Every step but the confirmation is a section of the page.',
        );
        self::assertSame(
            ['cart', 'delivery', 'payment'],
            $this->unlockedSectionsOf($crawler),
            'A cart with its delivery and its payment settled has nothing left locked.',
        );
        self::assertCount(
            0,
            $crawler->filter('#checkout-step h1'),
            'Each section already names its step above its content: a step component stacked here writes its own title one level down, or the page reads as three documents to a screen reader.',
        );

        $this->tickTheTermsAndConditions($crawler);

        $this->client->request('GET', '/checkout/pay');

        self::assertSame(
            1,
            OrderQuery::create()->filterByCartId($cart->getId())->count(),
            'The one-page checkout must place the same order the several-screen one does.',
        );
    }

    /**
     * The section that opens itself, without the page being asked for again.
     *
     * The re-render is the very request the browser sends after a step component says it
     * changed something: the component's own url, with the props the page was rendered
     * with. What comes back is what gets morphed into the page.
     */
    public function testASectionOfTheOnePageCheckoutOpensAsSoonAsTheCartEarnsIt(): void
    {
        $this->layOutTheCheckout(CheckoutDisplayMode::OnePage);

        $cart = $this->openACartIdentifiedButUndelivered();

        $crawler = $this->requestAPageThatMustRender('/checkout/cart');

        self::assertSame(
            ['cart', 'delivery'],
            $this->unlockedSectionsOf($crawler),
            'The payment section is locked while the delivery has not been settled.',
        );
        self::assertCount(
            0,
            $crawler->filter(\sprintf('[data-live-name-value="%s"]', self::PAYMENT_STEP_COMPONENT)),
            'A locked section holds nothing at all: its step component must not be in the page.',
        );

        $this->settleTheDeliveryOf($cart);

        $reRendered = $this->replayTheLiveRenderOf($crawler, self::ONE_PAGE_COMPONENT);

        self::assertSame(
            ['cart', 'delivery', 'payment'],
            $this->unlockedSectionsOf($reRendered),
            'Settling the delivery must open the payment section on the very next re-render.',
        );
        self::assertCount(
            1,
            $reRendered->filter(\sprintf('[data-live-name-value="%s"]', self::PAYMENT_STEP_COMPONENT)),
            'The section that just opened must now carry the step component it stands for.',
        );
    }

    /**
     * @return iterable<string, array{0: CheckoutDisplayMode, 1: string}>
     */
    public static function everyLayoutAndWhereItSendsACartWithNothingInIt(): iterable
    {
        yield 'several screens' => [CheckoutDisplayMode::Steps, '/checkout/cart'];
        yield 'one page' => [CheckoutDisplayMode::OnePage, '/checkout/cart'];
    }

    #[DataProvider('everyLayoutAndWhereItSendsACartWithNothingInIt')]
    public function testAStepTheCartHasNotReachedSendsTheBuyerToWhatIsLeftToDo(
        CheckoutDisplayMode $layout,
        string $expectedPath,
    ): void {
        $this->layOutTheCheckout($layout);

        // A session that may enter the checkout, so that what is being checked is the
        // progression and not the identification standing in front of it.
        $this->signInAsARealAccount();

        $this->client->request('GET', '/checkout/payment');

        $this->assertResponseRedirectsTo($expectedPath);
    }

    public function testACartWithNothingToShipIsNotAskedAboutItsDeliveryAndStillOrders(): void
    {
        $cart = $this->openACheckoutReadyCart(nothingToShip: true);

        $crawler = $this->requestAPageThatMustRender('/checkout/payment');

        self::assertSame(
            ['Your cart', 'Payment', 'Confirmation'],
            $this->stepsNamedBy($crawler),
            'A cart with nothing to ship must not be shown a delivery step it will never make.',
        );

        $this->client->request('GET', '/checkout/delivery');
        $this->assertResponseRedirectsTo('/checkout/payment');

        $this->tickTheTermsAndConditions($this->requestAPageThatMustRender('/checkout/payment'));

        $this->client->request('GET', '/checkout/pay');

        self::assertSame(
            1,
            OrderQuery::create()->filterByCartId($cart->getId())->count(),
            'The order is still refused without a carrier: the theme has to settle the one a virtual cart ships under.',
        );
    }

    /**
     * The bar of a page read after the order exists.
     *
     * The placement empties the cart, and an empty cart is not a cart with nothing to
     * ship: a tunnel redrawn from it puts the delivery step back, and the buyer is shown
     * a stop they were never taken to. What the confirmation draws has to be the tunnel
     * the order was placed through.
     */
    public function testTheConfirmationOfAnOrderWithNothingToShipDrawsTheTunnelItWasPlacedThrough(): void
    {
        $cart = $this->openACheckoutReadyCart(nothingToShip: true);

        $this->tickTheTermsAndConditions($this->requestAPageThatMustRender('/checkout/payment'));

        $this->client->request('GET', '/checkout/pay');

        self::assertSame(
            1,
            OrderQuery::create()->filterByCartId($cart->getId())->count(),
            'Nothing below says anything about a confirmation page unless the order was actually placed.',
        );

        // The state this page is actually read in: no cart content left anywhere. The
        // core empties the cart on the order, and a buyer coming back from a payment
        // gateway may come back without the session — and the cookie — they left with.
        // An empty cart is not a cart with nothing to ship, which is precisely why a bar
        // redrawn from the cart at this point puts the delivery step back.
        CartItemQuery::create()->deleteAll($this->getPropelConnection());
        $this->forgetHydratedCarts();

        $crawler = $this->requestAPageThatMustRender('/checkout/confirm');

        self::assertSame(
            ['Your cart', 'Payment', 'Confirmation'],
            $this->stepsNamedBy($crawler),
            'The bar must not gain the delivery step the buyer was never shown, just because the cart it was left out for is now empty.',
        );
    }

    /**
     * The one-page layout seen by somebody the session does not know yet.
     *
     * Every section past the cart used to be a screen the checkout guarded one at a
     * time; here they are panels of a page anybody may open, so the guard travels with
     * them — and a lock with no way out of it is a dead end.
     */
    public function testTheOnePageCheckoutLocksEverythingPastTheCartForAVisitorWithNoAccount(): void
    {
        $this->layOutTheCheckout(CheckoutDisplayMode::OnePage);

        $this->openASessionWithACart();

        $crawler = $this->requestAPageThatMustRender('/checkout/cart');

        self::assertSame(
            ['cart'],
            $this->unlockedSectionsOf($crawler),
            'A visitor with no account may fill their cart and nothing else.',
        );
        self::assertCount(
            0,
            $crawler->filter(\sprintf('[data-live-name-value="%s"]', self::PAYMENT_STEP_COMPONENT)),
            'A locked section holds nothing at all: its step component must not be in the page.',
        );
        self::assertGreaterThan(
            0,
            $crawler->filter('a[href="/checkout/identify"]')->count(),
            'The lock must offer the way out of it: the page that asks the visitor who they are.',
        );
    }

    /**
     * The several-screen checkout asks on every step whether a guest's cart still lets
     * them order without an account, since a product that requires one may be added
     * after the way in. The one-page layout has no steps to ask it on: its sections have
     * to carry that refusal themselves.
     */
    public function testTheOnePageCheckoutLocksEverythingPastTheCartForAGuestWhoseCartGainedAProductThatRequiresAnAccount(): void
    {
        $this->layOutTheCheckout(CheckoutDisplayMode::OnePage);
        $this->setGuestCheckoutMode(GuestCheckoutMode::EnabledUnlessProductForbids);

        $cart = $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));

        $fixtures = $this->fixtures();
        $product = $fixtures->product(
            $fixtures->category(),
            $fixtures->taxRule(),
            $fixtures->currency(),
            ['title' => 'A product that requires an account'],
        );
        $product->setGuestCheckoutForbidden(1)->save();
        $fixtures->cartItem($cart, $product);
        $this->forgetHydratedCarts();

        $crawler = $this->requestAPageThatMustRender('/checkout/cart');

        self::assertContains('delivery', $this->sectionsOf($crawler));
        self::assertSame(
            ['cart'],
            $this->unlockedSectionsOf($crawler),
            'A guest whose cart requires an account may fill their cart and nothing else.',
        );
        self::assertGreaterThan(
            0,
            $crawler->filter('a[href^="/customer/login"]')->count(),
            'The lock must offer the way out of it: signing in.',
        );
    }

    public function testTurningTheDeliveryStepOffTakesItOutOfTheWholeTunnel(): void
    {
        $this->setTheDeliveryStepActive(false);

        $cart = $this->openACheckoutReadyCart(nothingToShip: true);

        $crawler = $this->requestAPageThatMustRender('/checkout/payment');

        self::assertSame(
            ['Your cart', 'Payment', 'Confirmation'],
            $this->stepsNamedBy($crawler),
            'A step the merchant turned off must be gone from the bar.',
        );

        $this->client->request('GET', '/checkout/delivery');
        $this->assertResponseRedirectsTo('/checkout/payment');

        $this->tickTheTermsAndConditions($this->requestAPageThatMustRender('/checkout/payment'));

        $this->client->request('GET', '/checkout/pay');

        self::assertSame(
            1,
            OrderQuery::create()->filterByCartId($cart->getId())->count(),
            'A tunnel the merchant shortened must still be a tunnel an order comes out of.',
        );
    }

    /**
     * The entry of the bar that stands for the identification page, on that very page.
     *
     * It borrows the name of the step it stands in front of, and it used to borrow its
     * url too — which sends a visitor with no session straight back to the identification
     * page. A bar entry that lands on the page it was clicked from is a dead link with
     * extra steps, and the page this replaced wrote no href there for exactly that
     * reason.
     */
    public function testTheIdentificationEntryOfTheBarIsNotALinkThatComesBackToThePage(): void
    {
        $this->openASessionWithACart();

        $crawler = $this->requestIdentificationPage();

        self::assertGreaterThan(
            0,
            $crawler->filter('.CheckoutSteps a[href="/checkout/cart"]')->count(),
            'The bar of this page does draw links: without that, the assertion below would pass on a bar with no link at all.',
        );
        self::assertCount(
            0,
            $crawler->filter('.CheckoutSteps a[href="/checkout/delivery"]'),
            'The entry standing for the identification must not link to the step behind it: that step turns a visitor with no session straight back to this page.',
        );
    }

    /**
     * A step a module declares, in the several-screen layout, where this theme has no
     * screen to serve for it.
     *
     * Read as a unit test of the resolver and built by hand: the step providers are a
     * tagged iterator read at compile time, so a fixture one cannot be added to the
     * container of a booted kernel — and the kernel of this suite is deliberately never
     * rebooted. What is checked is the resolver's own answer, which is where the loop was:
     * a step with no url of its own was answered with the start of the tunnel, and the
     * cart's own "next" link points straight back at the step after it. The buyer is held
     * at the last screen they can act on instead.
     */
    public function testAStepWithNoScreenInThisThemeHoldsTheBuyerAtTheLastScreenThereIs(): void
    {
        $this->layOutTheCheckout(CheckoutDisplayMode::Steps);
        $this->seedTheRowOfAModuleStepAfterTheDelivery();

        $resolver = new CheckoutStepRouteResolver(
            $this->aProgressionOfATunnelHolding([
                CheckoutStep::CODE_CART => true,
                CheckoutStep::CODE_DELIVERY => true,
                self::MODULE_STEP_CODE => false,
                CheckoutStep::CODE_PAYMENT => true,
                CheckoutStep::CODE_CONFIRMATION => true,
            ]),
            static::getContainer()->get('router'),
        );

        self::assertSame(
            '/checkout/delivery',
            $resolver->pathOfTheFirstIncompleteStep(new Cart()),
            'A module step with no screen here must send the buyer to the last step before it that has one, and not back to the cart the "next" link would walk them forward from.',
        );
    }

    /**
     * The same resolver, on the case that must not change: a step this theme does serve a
     * screen for is answered with its own url.
     */
    public function testAStepThisThemeServesIsStillAnsweredWithItsOwnScreen(): void
    {
        $this->layOutTheCheckout(CheckoutDisplayMode::Steps);
        $this->seedTheRowOfAModuleStepAfterTheDelivery();

        $resolver = new CheckoutStepRouteResolver(
            $this->aProgressionOfATunnelHolding([
                CheckoutStep::CODE_CART => true,
                CheckoutStep::CODE_DELIVERY => false,
                self::MODULE_STEP_CODE => false,
                CheckoutStep::CODE_PAYMENT => true,
                CheckoutStep::CODE_CONFIRMATION => true,
            ]),
            static::getContainer()->get('router'),
        );

        self::assertSame(
            '/checkout/delivery',
            $resolver->pathOfTheFirstIncompleteStep(new Cart()),
        );
    }

    public function testTheOnePageCheckoutHasNoSectionForAStepTheShopTookOut(): void
    {
        $this->layOutTheCheckout(CheckoutDisplayMode::OnePage);
        $this->setTheDeliveryStepActive(false);

        $this->openACheckoutReadyCart(nothingToShip: true);

        $crawler = $this->requestAPageThatMustRender('/checkout/cart');

        self::assertCount(
            0,
            $crawler->filter('.CheckoutSteps'),
            'The one-page layout carries no progression bar, shortened tunnel included.',
        );
        self::assertSame(
            ['cart', 'payment'],
            $this->sectionsOf($crawler),
            'A step that is not in the tunnel has no section on the page either.',
        );
    }

    // ------------------------------------------------------------------
    // Reading the page
    // ------------------------------------------------------------------

    private function requestAPageThatMustRender(string $path): Crawler
    {
        $crawler = $this->client->request('GET', $path);

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            \sprintf('"%s" must be served with a 200, or nothing below proves anything.', $path),
        );

        return $crawler;
    }

    /**
     * The wordings of the progression bar, in the order they are drawn.
     *
     * @return list<string>
     */
    private function stepsNamedBy(Crawler $crawler): array
    {
        return $crawler
            ->filter('.CheckoutSteps .Step .Step-label')
            ->each(static fn (Crawler $label): string => trim($label->text()));
    }

    /**
     * The sections of the one-page layout, by step code.
     *
     * @return list<string>
     */
    private function sectionsOf(Crawler $crawler): array
    {
        return $crawler
            ->filter(self::SECTION_SELECTOR)
            ->each(static fn (Crawler $item): string => (string) $item->attr('data-value'));
    }

    /**
     * The sections a buyer may act on: the accordion disables the trigger of the others,
     * which is the same attribute a keyboard and a screen reader go by.
     *
     * @return list<string>
     */
    private function unlockedSectionsOf(Crawler $crawler): array
    {
        return $crawler
            ->filter(self::SECTION_SELECTOR)
            ->reduce(static fn (Crawler $item): bool => null === $item->attr('aria-disabled'))
            ->each(static fn (Crawler $item): string => (string) $item->attr('data-value'));
    }

    /**
     * Asks a live component to render itself again, the way the browser does after a
     * sibling says something changed: its own url, and the props the page carries.
     */
    private function replayTheLiveRenderOf(Crawler $crawler, string $componentName): Crawler
    {
        $component = $crawler->filter(\sprintf('[data-live-name-value="%s"]', $componentName))->first();

        self::assertCount(1, $component, \sprintf('The page carries no "%s" live component to re-render.', $componentName));

        // POST and no action name: that is the component's default action, which renders
        // it again and nothing else. A GET would be turned away — components are POST
        // only unless they say otherwise.
        $rendered = $this->client->request(
            'POST',
            (string) $component->attr('data-live-url-value'),
            ['data' => json_encode([
                'props' => json_decode((string) $component->attr('data-live-props-value'), true, 512, \JSON_THROW_ON_ERROR),
                'updated' => new \stdClass(),
            ], \JSON_THROW_ON_ERROR)],
            [],
            [
                'HTTP_ACCEPT' => 'application/vnd.live-component+html',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ],
        );

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'The re-render must answer, or nothing the buyer does reaches the page.',
        );

        return $rendered;
    }

    /**
     * Ticks the box the shop refuses an order without, the way the browser does: the
     * action, its arguments and the component to send them to are all read off the input
     * the page rendered.
     */
    private function tickTheTermsAndConditions(Crawler $crawler): void
    {
        $box = $crawler->filter(\sprintf(
            'input[data-live-action-param="toggleConsent"][data-live-code-param="%s"]',
            Consent::CODE_TERMS_AND_CONDITIONS,
        ));

        self::assertCount(1, $box, 'The payment step must carry the box the order is refused without.');

        $component = $box->ancestors()->filter('[data-live-url-value]')->first();

        self::assertCount(1, $component, 'The box must live inside a live component for its answer to reach the server.');

        $this->client->request(
            'POST',
            \sprintf('%s/%s', $component->attr('data-live-url-value'), $box->attr('data-live-action-param')),
            ['data' => json_encode([
                'props' => json_decode((string) $component->attr('data-live-props-value'), true, 512, \JSON_THROW_ON_ERROR),
                'updated' => new \stdClass(),
                'args' => [
                    'code' => $box->attr('data-live-code-param'),
                    'accepted' => json_decode((string) $box->attr('data-live-accepted-param'), true, 512, \JSON_THROW_ON_ERROR),
                ],
            ], \JSON_THROW_ON_ERROR)],
            [],
            [
                'HTTP_ACCEPT' => 'application/vnd.live-component+html',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ],
        );

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'The action behind the box must answer, or the answer is never recorded.',
        );
    }

    // ------------------------------------------------------------------
    // Setting the shop up
    // ------------------------------------------------------------------

    private function layOutTheCheckout(CheckoutDisplayMode $mode): void
    {
        // Written rather than rolled back with the transaction: ConfigQuery keeps the
        // value in a static cache that the request handlers of this very process read.
        ConfigQuery::write('checkout_display_mode', $mode->value);
    }

    private function setTheDeliveryStepActive(bool $active): void
    {
        $step = CheckoutStepQuery::create()
            ->filterByCode(CheckoutStep::CODE_DELIVERY)
            ->findOne($this->getPropelConnection())
            ?? self::fail('The shop must ship with a delivery step to turn off.');

        $step->setActive($active ? 1 : 0)->save($this->getPropelConnection());

        if (!$active) {
            $this->deliveryStepWasTurnedOff = true;
        }

        $this->forgetWhatTheKernelIsHoldingOnTo();
    }

    /**
     * A session standing in the checkout with everything but the consents settled.
     *
     * The way in is the one a guest takes — the cart page, then the identification form
     * — because that is what puts a customer and a cart in the session. What the delivery
     * step would then have written on the cart is written here instead: these tests are
     * about which steps there are, and walking a step through its own live components
     * would only add ways for them to fail for another reason.
     */
    private function openACheckoutReadyCart(bool $nothingToShip = false): Cart
    {
        $cart = $this->openACartIdentifiedButUndelivered($nothingToShip);

        $this->settleTheDeliveryOf($cart);

        return $cart;
    }

    /**
     * The same session, stopped one step earlier: identified, with a cart the shop can
     * deliver to, and no carrier chosen.
     */
    private function openACartIdentifiedButUndelivered(bool $nothingToShip = false): Cart
    {
        $cart = $this->openASessionWithACart();

        if ($nothingToShip) {
            $this->makeEverythingInTheCartVirtual($cart);
        }

        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));

        $connection = $this->getPropelConnection();

        // The cart was read before the identification handed it to the guest account.
        $this->forgetHydratedCarts();
        $cart = CartQuery::create()->findPk($cart->getId(), $connection)
            ?? self::fail('The cart the session was filling is gone.');

        $address = AddressQuery::create()
            ->filterByCustomerId($cart->getCustomerId())
            ->findOne($connection)
            ?? self::fail('The identification form must have written the address the buyer typed.');

        $this->serveTheCountryOf($address);

        $cart
            ->setAddressInvoiceId($this->copyToCartAddress($address)->getId())
            ->setPaymentModuleId($this->moduleNamed('Cheque'))
            ->save($connection);

        // Fixture products come out of stock, and the checkout sends a cart it cannot
        // fulfil back to the cart page before it ever looks at the rest.
        foreach ($cart->getCartItems() as $cartItem) {
            $cartItem->getProductSaleElements()->setQuantity(100)->save($connection);
        }

        $this->forgetHydratedCarts();

        $this->answerTheDeliveryQuote();
        $this->acceptEveryPayment();

        return $cart;
    }

    /**
     * Writes onto the cart what choosing an address and a carrier writes.
     *
     * A cart with nothing to ship is left alone: its delivery is the theme's job, on the
     * step that follows the cart, and settling it here would be checking the test's own
     * work instead of the theme's.
     */
    private function settleTheDeliveryOf(Cart $cart): void
    {
        $connection = $this->getPropelConnection();

        $this->forgetHydratedCarts();
        $cart = CartQuery::create()->findPk($cart->getId(), $connection)
            ?? self::fail('The cart the session was filling is gone.');

        if ($cart->isVirtual()) {
            return;
        }

        $address = AddressQuery::create()
            ->filterByCustomerId($cart->getCustomerId())
            ->findOne($connection)
            ?? self::fail('The identification form must have written the address the buyer typed.');

        $cart
            ->setAddressDeliveryId($this->copyToCartAddress($address)->getId())
            ->setDeliveryModuleId($this->moduleNamed('CustomDelivery'))
            ->setPostage(self::QUOTED_POSTAGE)
            ->setPostageTax(self::QUOTED_POSTAGE_TAX)
            ->setPostageTaxRuleTitle('VAT 20')
            ->save($connection);

        $this->forgetHydratedCarts();
    }

    private function makeEverythingInTheCartVirtual(Cart $cart): void
    {
        $connection = $this->getPropelConnection();

        $this->forgetHydratedCarts();
        $cart = CartQuery::create()->findPk($cart->getId(), $connection)
            ?? self::fail('The cart the session was filling is gone.');

        foreach ($cart->getCartItems() as $cartItem) {
            // Both halves of what makes a cart virtual: a product the shop marked as
            // such, and a sale element with no weight to ship.
            $cartItem->getProductSaleElements()->setWeight(0)->save($connection);
            $cartItem->getProductSaleElements()->getProduct()->setVirtual(1)->save($connection);
        }

        $this->forgetHydratedCarts();
    }

    private function moduleNamed(string $code): int
    {
        $module = ModuleQuery::create()->findOneByCode($code)
            ?? self::fail(\sprintf('No "%s" module installed — run bin/test-prepare.', $code));

        return (int) $module->getId();
    }

    private function copyToCartAddress(Address $source): CartAddress
    {
        $address = (new CartAddress())
            // What CartFacade resolves the customer address from when the order is built.
            ->setAddressId($source->getId())
            ->setCustomerTitleId($source->getTitleId())
            ->setFirstname((string) $source->getFirstname())
            ->setLastname((string) $source->getLastname())
            ->setAddress1((string) $source->getAddress1())
            ->setZipcode((string) $source->getZipcode())
            ->setCity((string) $source->getCity())
            ->setCellphone($source->getCellphone())
            ->setCountryId($source->getCountryId());
        $address->save($this->getPropelConnection());

        return $address;
    }

    /**
     * Puts the buyer's country in an area the delivery module serves, which is what the
     * delivery guard asks before it quotes anything.
     */
    private function serveTheCountryOf(Address $address): void
    {
        $connection = $this->getPropelConnection();

        $area = (new Area())->setName('Configurable checkout test area');
        $area->save($connection);

        (new CountryArea())
            ->setAreaId($area->getId())
            ->setCountryId($address->getCountryId())
            ->save($connection);

        (new AreaDeliveryModule())
            ->setAreaId($area->getId())
            ->setDeliveryModuleId($this->moduleNamed('CustomDelivery'))
            ->save($connection);
    }

    private function answerTheDeliveryQuote(): void
    {
        $this->listen(
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
            static function (DeliveryPostageEvent $event): void {
                $event->setValidModule(true);
                $event->setPostage(new OrderPostage(
                    (float) self::QUOTED_POSTAGE,
                    (float) self::QUOTED_POSTAGE_TAX,
                    'VAT 20',
                ));
                $event->stopPropagation();
            },
            512,
        );
    }

    private function acceptEveryPayment(): void
    {
        $this->listen(
            TheliaEvents::MODULE_PAYMENT_IS_VALID,
            static function (IsValidPaymentEvent $event): void {
                $event->setValidModule(true);
                // Read by the payment listing the step renders, and left uninitialised
                // by a listener that answers before the module does.
                $event->setMinimumAmount(0);
                $event->setMaximumAmount(0);
                $event->stopPropagation();
            },
            512,
        );
    }

    private function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        static::getContainer()->get('event_dispatcher')->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    /**
     * Drops everything this process is carrying between two requests.
     *
     * The whole suite runs in one process with the kernel kept alive, so a row a request
     * hydrated is handed back as it was to the next one — a checkout step this test just
     * turned off, a cart whose carrier it just wrote — and the progression memoizes what
     * it answered about a cart on top of that. A browser gets a fresh read every time;
     * this is how the test does too.
     */
    private function forgetWhatTheKernelIsHoldingOnTo(): void
    {
        static::getContainer()->get(CheckoutProgressionService::class)->forget();
        static::getContainer()->get(ConsentProvider::class)->forgetCache();

        CheckoutStepTableMap::clearInstancePool();
        CheckoutStepTableMap::clearRelatedInstancePool();
        ConsentTableMap::clearInstancePool();
        ConsentTableMap::clearRelatedInstancePool();

        $this->forgetHydratedCarts();
    }

    private function forgetHydratedCarts(): void
    {
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
        ProductSaleElementsTableMap::clearInstancePool();
        ProductSaleElementsTableMap::clearRelatedInstancePool();
    }

    // ------------------------------------------------------------------
    // A tunnel holding a step no theme has a screen for
    // ------------------------------------------------------------------

    /**
     * Rows for the tunnel the fixture providers below describe, rolled back with the
     * test's transaction.
     *
     * The row is what carries the position, so it is not optional here: left to the
     * provider's own default, the module step would land next to the payment, and a
     * tunnel that does not take the money next to last is one the progression repairs
     * instead of reading.
     */
    private function seedTheRowOfAModuleStepAfterTheDelivery(): void
    {
        $connection = $this->getPropelConnection();

        (new CheckoutStep())
            ->setCode(self::MODULE_STEP_CODE)
            ->setPosition(3)
            ->setActive(1)
            ->setMandatory(0)
            ->save($connection);

        foreach ([CheckoutStep::CODE_PAYMENT => 4, CheckoutStep::CODE_CONFIRMATION => 5] as $code => $position) {
            $step = CheckoutStepQuery::create()->filterByCode($code)->findOne($connection)
                ?? self::fail(\sprintf('The shop must ship with a "%s" step.', $code));

            $step->setPosition($position)->save($connection);
        }

        $this->forgetWhatTheKernelIsHoldingOnTo();
    }

    /**
     * A progression reading the tunnel of fixture providers, one per code, each either
     * settled or not.
     *
     * @param array<string, bool> $settledByCode in tunnel order
     */
    private function aProgressionOfATunnelHolding(array $settledByCode): CheckoutProgressionService
    {
        $providers = [];

        foreach ($settledByCode as $code => $settled) {
            $providers[] = $this->aStepProvider($code, $settled);
        }

        return new CheckoutProgressionService(
            $providers,
            static::getContainer()->get(CheckoutTunnelShape::class),
            static::getContainer()->get(CheckoutStepTitleResolver::class),
            new NullLogger(),
        );
    }

    private function aStepProvider(string $code, bool $settled): CheckoutStepProviderInterface
    {
        return new class($code, $settled) implements CheckoutStepProviderInterface {
            public function __construct(
                private readonly string $stepCode,
                private readonly bool $settled,
            ) {
            }

            public function code(): string
            {
                return $this->stepCode;
            }

            public function defaultPosition(): int
            {
                return 1;
            }

            public function isMandatory(): bool
            {
                return false;
            }

            public function isSkippedFor(Cart $cart): bool
            {
                return false;
            }

            public function check(Cart $cart): void
            {
                if ($this->settled) {
                    return;
                }

                throw new class('This fixture step is not settled.') extends CheckoutException {};
            }

            public function componentName(): ?string
            {
                return null;
            }
        };
    }
}
