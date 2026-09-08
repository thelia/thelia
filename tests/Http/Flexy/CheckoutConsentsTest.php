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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartQuery;
use Thelia\Model\Consent;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Content;
use Thelia\Model\CountryArea;
use Thelia\Model\Lang;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\ConsentTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;

/**
 * The consents of the payment step, seen from a browser.
 *
 * What the theme owes the buyer is one box per consent the shop is asking for, and what
 * it owes the shop is an order that does not get placed while a required box is
 * unticked. The second one is the rule: the greyed-out button is a convenience, and a
 * request that reaches /checkout/pay without going through it must be turned away all
 * the same. That is what most of this file is about.
 *
 * The shop the tests run against ships one consent, the terms and conditions of sale,
 * mandatory and active. Everything below starts from it.
 */
final class CheckoutConsentsTest extends GuestCheckoutTestCase
{
    /**
     * The payment step component of a theme that asks for consents. A theme published
     * before this feature has the class without the action.
     */
    private const PAYMENT_STEP_COMPONENT = 'FlexyBundle\Components\Organisms\Payment\Base';

    private const QUOTED_POSTAGE = '12.000000';

    private const QUOTED_POSTAGE_TAX = '2.000000';

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    /**
     * Skipped before anything is booted: the core is released with whichever version of
     * the theme it is given, and the published one may know nothing about consents.
     */
    protected function setUp(): void
    {
        if (!method_exists(self::PAYMENT_STEP_COMPONENT, 'toggleConsent')) {
            self::markTestSkipped('The installed theme asks for no consents on its payment step.');
        }

        parent::setUp();

        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        // ConsentProvider memoizes the active consents on the service instance for the
        // life of the request, invalidated only by the domain events a real edit fires.
        // This suite writes straight to the Propel model to reword or attach a content to
        // the shop's consent, which fires none of them — and the kernel is kept warm
        // across every test in this class, so a consent object cached by an earlier test
        // would otherwise be handed to this one, columns and all, after the transaction
        // that made them true has been rolled back.
        static::getContainer()->get(ConsentProvider::class)->forgetCache();

        // Propel's own instance pool is a second, lower-level place the same staleness
        // hides: a Consent object a previous test's request left pooled — the payment
        // step renders one on every request — is handed back whole to a plain findOne()
        // in this one, content_id and all, once that column's real value has already
        // been rolled back underneath it.
        $this->forgetHydratedConsents();
    }

    /**
     * Survives the skip above: the list is empty then, so nothing here asks for a
     * container that was never booted.
     */
    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            static::getContainer()->get('event_dispatcher')->removeListener($eventName, $listener);
        }

        $this->registeredListeners = [];

        parent::tearDown();
    }

    public function testThePaymentStepAsksTheBuyerToAcceptTheTermsAndConditions(): void
    {
        $this->openACheckoutReadyCart();

        $crawler = $this->requestThePaymentStep();
        $box = $this->consentBoxOf($crawler, Consent::CODE_TERMS_AND_CONDITIONS);

        self::assertCount(1, $box, 'The payment step must carry a box for the consent the shop asks for.');
        self::assertNotNull($box->attr('required'), 'A consent the order is refused without is a required field.');
        self::assertNull($box->attr('checked'), 'Nothing is accepted on the buyer\'s behalf.');
        self::assertStringContainsString(
            'I have read and accept the terms and conditions of sale',
            $this->labelOf($box),
            'The box must be labelled with the wording the shop configured.',
        );
    }

    public function testTheWordingIsPrintedAsTextRatherThanAsMarkup(): void
    {
        $this->openACheckoutReadyCart();

        $this->rewordTheTermsAndConditions('<em>Accept</em> them');

        $crawler = $this->requestThePaymentStep();

        self::assertStringContainsString(
            '<em>Accept</em> them',
            $this->labelOf($this->consentBoxOf($crawler, Consent::CODE_TERMS_AND_CONDITIONS)),
            'The wording is text: markup a shop administrator types must reach the page escaped.',
        );
        self::assertStringNotContainsString(
            '<em>Accept</em> them',
            (string) $this->client->getResponse()->getContent(),
            'Nothing may put that wording into the page as live markup.',
        );
    }

    public function testTheExplanationTheShopWroteIsShownUnderTheBoxAndTiedToIt(): void
    {
        $this->openACheckoutReadyCart();

        $this->explainTheTermsAndConditions('The full text is in the contract you were sent.');

        $crawler = $this->requestThePaymentStep();
        $box = $this->consentBoxOf($crawler, Consent::CODE_TERMS_AND_CONDITIONS);
        $describedBy = $box->attr('aria-describedby');

        self::assertNotNull($describedBy, 'The explanation must be announced with the box, not just placed near it.');
        self::assertSame(
            'The full text is in the contract you were sent.',
            $crawler->filter('#'.$describedBy)->text(),
            'The box must point at the explanation the shop wrote.',
        );
    }

    public function testTheBoxPointsAtTheContentHoldingTheTermsWhenTheShopPublishedOne(): void
    {
        $this->openACheckoutReadyCart();

        $content = $this->publishTheTermsAsAContent();

        $crawler = $this->requestThePaymentStep();
        $links = $this->consentBlockOf($crawler, Consent::CODE_TERMS_AND_CONDITIONS)->filter('a');

        self::assertCount(1, $links, 'A consent that names a content must be given a link to read it.');
        self::assertSame(
            $content->getUrl(Lang::getDefaultLanguage()->getLocale()),
            $links->attr('href'),
            'The link must lead to the content the consent points at.',
        );
    }

    /**
     * The rule, called the way a buyer who never saw the button would call it.
     */
    public function testAnOrderIsRefusedWhileTheTermsAndConditionsAreNotAccepted(): void
    {
        $cart = $this->openACheckoutReadyCart();

        $this->client->request('GET', '/checkout/pay');

        $this->assertResponseRedirectsTo('/checkout/payment');
        self::assertSame(
            0,
            OrderQuery::create()->filterByCartId($cart->getId())->count(),
            'A request that skips the boxes must place no order at all.',
        );
    }

    /**
     * The refusal is silent otherwise: a buyer who typed the url, or whose browser
     * skipped the button, is sent back to the payment step with nothing telling them
     * why. The message must name the consent, and a title a shop administrator typed
     * with markup in it must not turn into markup on the page.
     */
    public function testAnOrderIsRefusedWithAMessageNamingTheMissingConsent(): void
    {
        $this->openACheckoutReadyCart();
        $this->rewordTheTermsAndConditions('<em>foo</em>');

        $this->client->request('GET', '/checkout/pay');
        $this->assertResponseRedirectsTo('/checkout/payment');

        $crawler = $this->client->followRedirect();

        self::assertStringContainsString(
            'You must accept',
            $crawler->text(),
            'The payment step must show the reason the order was refused.',
        );
        self::assertStringContainsString(
            '&lt;em&gt;foo&lt;/em&gt;',
            (string) $this->client->getResponse()->getContent(),
            'The consent title must reach the page escaped.',
        );
        self::assertStringNotContainsString(
            '<em>foo</em>',
            (string) $this->client->getResponse()->getContent(),
            'A title typed in the back office must never become live markup on the page.',
        );
    }

    public function testTheOrderGoesThroughOnceTheBoxIsTicked(): void
    {
        $cart = $this->openACheckoutReadyCart();

        $this->tickTheBoxOf($this->requestThePaymentStep(), Consent::CODE_TERMS_AND_CONDITIONS);

        $this->client->request('GET', '/checkout/pay');

        self::assertSame(
            1,
            OrderQuery::create()->filterByCartId($cart->getId())->count(),
            'An answered consent must stop standing in the way of the order.',
        );
    }

    /**
     * The box the buyer ticked and then thought better of.
     *
     * Each box asks for the answer opposite to the one the page was rendered with, so
     * clicking it twice takes the acceptance back rather than sending it twice — and the
     * order is refused again.
     */
    public function testUntickingTheBoxTakesTheAcceptanceBack(): void
    {
        $cart = $this->openACheckoutReadyCart();

        $this->tickTheBoxOf($this->requestThePaymentStep(), Consent::CODE_TERMS_AND_CONDITIONS);
        $this->tickTheBoxOf($this->requestThePaymentStep(), Consent::CODE_TERMS_AND_CONDITIONS);

        $this->client->request('GET', '/checkout/pay');

        $this->assertResponseRedirectsTo('/checkout/payment');
        self::assertSame(
            0,
            OrderQuery::create()->filterByCartId($cart->getId())->count(),
            'A buyer who unticked the box has not accepted anything.',
        );
    }

    public function testAConsentTheShopDoesNotRequireIsOfferedWithoutBlockingTheOrder(): void
    {
        $cart = $this->openACheckoutReadyCart();

        $this->createConsent('newsletter-test', 'Send me the newsletter', mandatory: false);

        $crawler = $this->requestThePaymentStep();
        $optionalBox = $this->consentBoxOf($crawler, 'newsletter-test');

        self::assertCount(1, $optionalBox, 'An optional consent is asked for too.');
        self::assertNull($optionalBox->attr('required'), 'It must not be marked as a field the buyer has to fill.');

        // Only the mandatory one is answered: the optional box is left as it came.
        $this->tickTheBoxOf($crawler, Consent::CODE_TERMS_AND_CONDITIONS);

        $this->client->request('GET', '/checkout/pay');

        self::assertSame(
            1,
            OrderQuery::create()->filterByCartId($cart->getId())->count(),
            'A box the shop does not require must not hold the order back.',
        );
    }

    private function requestThePaymentStep(): Crawler
    {
        $crawler = $this->client->request('GET', '/checkout/payment');

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'The payment step must be served with a 200, or nothing below proves anything.',
        );

        return $crawler;
    }

    /**
     * The box of one consent, found by the action it carries rather than by a class name.
     */
    private function consentBoxOf(Crawler $crawler, string $code): Crawler
    {
        return $crawler->filter(\sprintf(
            'input[data-live-action-param="toggleConsent"][data-live-code-param="%s"]',
            $code,
        ));
    }

    /**
     * Everything shown for one consent: its box, its label and whatever is offered next
     * to them.
     */
    private function consentBlockOf(Crawler $crawler, string $code): Crawler
    {
        return $this->consentBoxOf($crawler, $code)->ancestors()->filter('li')->first();
    }

    private function labelOf(Crawler $box): string
    {
        return $box->ancestors()->filter('label')->first()->text();
    }

    /**
     * Ticks a box the way the browser does.
     *
     * Nothing is invented here: the action, its arguments and the component to send them
     * to are all read off the input the page rendered, which is what the Stimulus
     * controller behind it does with the same attributes. A box the theme renders
     * without them cannot be ticked by this either.
     */
    private function tickTheBoxOf(Crawler $crawler, string $code): void
    {
        $box = $this->consentBoxOf($crawler, $code);

        self::assertCount(1, $box, \sprintf('There is no box to tick for the "%s" consent.', $code));

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

    /**
     * A session standing at the payment step with everything but the consents settled.
     *
     * The way in is the one a guest takes — the cart page, then the identification form
     * — because that is what puts a customer and a cart in the session. What the
     * delivery step would then have set on the cart is written here instead: this file
     * is about the last step, and walking the previous one through its own live
     * components would only add ways for these tests to fail for another reason.
     */
    private function openACheckoutReadyCart(): Cart
    {
        $cart = $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));

        $connection = $this->getPropelConnection();

        // The cart was read before the identification handed it to the guest account.
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
        $cart = CartQuery::create()->findPk($cart->getId(), $connection)
            ?? self::fail('The cart the session was filling is gone.');

        $address = AddressQuery::create()
            ->filterByCustomerId($cart->getCustomerId())
            ->findOne($connection)
            ?? self::fail('The identification form must have written the address the buyer typed.');

        $this->serveTheCountryOf($address);

        $cart
            ->setAddressDeliveryId($this->copyToCartAddress($address)->getId())
            ->setAddressInvoiceId($this->copyToCartAddress($address)->getId())
            ->setDeliveryModuleId($this->moduleNamed('CustomDelivery'))
            ->setPaymentModuleId($this->moduleNamed('Cheque'))
            ->setPostage(self::QUOTED_POSTAGE)
            ->setPostageTax(self::QUOTED_POSTAGE_TAX)
            ->setPostageTaxRuleTitle('VAT 20')
            ->save($connection);

        // Fixture products come out of stock, and the checkout sends a cart it cannot
        // fulfil back to the cart page before it ever looks at the consents.
        foreach ($cart->getCartItems() as $cartItem) {
            $cartItem->getProductSaleElements()->setQuantity(100)->save($connection);
        }

        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
        ProductSaleElementsTableMap::clearInstancePool();
        ProductSaleElementsTableMap::clearRelatedInstancePool();

        $this->answerTheDeliveryQuote();
        $this->acceptEveryPayment();

        return $cart;
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

        $area = (new Area())->setName('Checkout consents test area');
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

    private function createConsent(string $code, string $title, bool $mandatory): Consent
    {
        $consent = (new Consent())
            ->setCode($code)
            ->setMandatory($mandatory ? 1 : 0)
            ->setActive(1)
            ->setLocale(Lang::getDefaultLanguage()->getLocale())
            ->setTitle($title);
        $consent->save($this->getPropelConnection());

        $this->forgetHydratedConsents();

        return $consent;
    }

    private function rewordTheTermsAndConditions(string $title): void
    {
        $this->termsAndConditions()
            ->setLocale(Lang::getDefaultLanguage()->getLocale())
            ->setTitle($title)
            ->save($this->getPropelConnection());

        $this->forgetHydratedConsents();
    }

    private function explainTheTermsAndConditions(string $description): void
    {
        $this->termsAndConditions()
            ->setLocale(Lang::getDefaultLanguage()->getLocale())
            ->setDescription($description)
            ->save($this->getPropelConnection());

        $this->forgetHydratedConsents();
    }

    private function publishTheTermsAsAContent(): Content
    {
        $fixtures = $this->fixtures();
        $content = $fixtures->content($fixtures->folder());

        $this->termsAndConditions()
            ->setContentId($content->getId())
            ->save($this->getPropelConnection());

        $this->forgetHydratedConsents();

        return $content;
    }

    private function termsAndConditions(): Consent
    {
        return ConsentQuery::create()
            ->filterByCode(Consent::CODE_TERMS_AND_CONDITIONS)
            ->findOne($this->getPropelConnection())
            ?? self::fail('The shop must ship with the terms and conditions consent.');
    }

    /**
     * The request handlers run in this very process, so a consent this test just changed
     * would otherwise be handed back to them as it was read before.
     */
    private function forgetHydratedConsents(): void
    {
        ConsentTableMap::clearInstancePool();
        ConsentTableMap::clearRelatedInstancePool();
    }

    private function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
