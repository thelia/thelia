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
use Thelia\Domain\Checkout\Service\GiftWrappingProvider;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\GiftWrapping;
use Thelia\Model\Lang;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\GiftWrappingTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\TaxRuleQuery;

/**
 * The gift block of the payment step, seen from a browser.
 *
 * The rule the shop cares about is the one the server enforces: only an identifier it
 * offers is accepted, the price is never read off the request, and a note over the limit
 * is refused rather than cut. The rule the buyer cares about is that a shop offering no
 * wrapping shows them nothing at all.
 */
final class CheckoutGiftWrappingTest extends GuestCheckoutTestCase
{
    /**
     * The gift block of a theme that knows about wrappings. A theme published before the
     * feature has none of it.
     */
    private const GIFT_COMPONENT = 'FlexyBundle\Components\Organisms\GiftWrapping\Base';

    private const QUOTED_POSTAGE = '12.000000';

    private const QUOTED_POSTAGE_TAX = '2.000000';

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        if (!class_exists(self::GIFT_COMPONENT)) {
            self::markTestSkipped('The installed theme offers no gift wrapping on its payment step.');
        }

        parent::setUp();

        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->turnMandatoryConsentsOff();

        // The provider memoizes the active wrappings for the life of a request, and the
        // kernel is kept warm across the tests of this class: a list cached by an earlier
        // one would otherwise be handed to this one after its transaction was rolled back.
        static::getContainer()->get(GiftWrappingProvider::class)->forgetCache();
        $this->forgetHydratedGiftWrappings();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            static::getContainer()->get('event_dispatcher')->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        parent::tearDown();
    }

    /**
     * The shop that sells no wrapping. Not an empty block, not a lone heading: nothing.
     */
    public function testAShopOfferingNoWrappingShowsNothingAtAll(): void
    {
        $this->openACheckoutReadyCart();

        $crawler = $this->requestThePaymentStep();

        self::assertCount(0, $this->wrappingChoices($crawler), 'No wrapping active, no choice rendered.');
        self::assertStringNotContainsString('Wrap it as a gift', $crawler->text());
    }

    public function testTheActiveWrappingsAreOfferedWithTheirPrice(): void
    {
        $this->openACheckoutReadyCart();
        $this->createGiftWrapping('gift-box', '3.000000', 'Gift box');
        $this->createGiftWrapping('kraft-paper', '0.000000', 'Kraft paper');

        $crawler = $this->requestThePaymentStep();

        // Two wrappings plus the "no wrapping" option the buyer needs to change their mind.
        self::assertCount(3, $this->wrappingChoices($crawler));
        self::assertStringContainsString('Gift box', $crawler->text());
        self::assertStringContainsString('Kraft paper', $crawler->text());
    }

    public function testAWrappingTurnedOffIsNotOffered(): void
    {
        $this->openACheckoutReadyCart();
        $wrapping = $this->createGiftWrapping('gift-box', '3.000000', 'Gift box');
        $wrapping->setActive(0)->save($this->getPropelConnection());
        $this->forgetHydratedGiftWrappings();
        static::getContainer()->get(GiftWrappingProvider::class)->forgetCache();

        $crawler = $this->requestThePaymentStep();

        self::assertCount(0, $this->wrappingChoices($crawler));
    }

    /**
     * The wording is written in the back office and printed on the payment page: it is
     * text, and markup typed into it must reach the browser escaped.
     */
    public function testTheWordingIsPrintedAsTextRatherThanAsMarkup(): void
    {
        $this->openACheckoutReadyCart();
        $this->createGiftWrapping('gift-box', '3.000000', '<em>Gift</em> box');

        $this->requestThePaymentStep();

        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('&lt;em&gt;Gift&lt;/em&gt; box', $html);
        self::assertStringNotContainsString('<em>Gift</em> box', $html);
    }

    public function testPickingAWrappingRecordsItOnTheCartAndTheOrderIsInvoicedForIt(): void
    {
        $cart = $this->openACheckoutReadyCart();
        $wrapping = $this->createGiftWrapping('gift-box', '3.000000', 'Gift box');

        $this->pickTheWrapping($this->requestThePaymentStep(), (int) $wrapping->getId());

        self::assertSame((int) $wrapping->getId(), (int) $this->reread($cart)->getGiftWrappingId());

        $this->client->request('GET', '/checkout/pay');

        $orderId = (int) OrderQuery::create()
            ->filterByCartId($cart->getId())
            ->findOne($this->getPropelConnection())
            ?->getId();

        self::assertNotSame(0, $orderId, 'The order must have been placed.');

        $line = OrderProductQuery::create()
            ->filterByOrderId($orderId)
            ->filterByLineType(OrderProduct::LINE_TYPE_SERVICE)
            ->findOne($this->getPropelConnection());

        self::assertInstanceOf(OrderProduct::class, $line);
        self::assertSame('Gift box', $line->getTitle());
        self::assertSame(3.0, (float) $line->getPrice());
    }

    /**
     * Nothing the browser sends carries an amount, and an identifier the shop does not
     * offer buys nothing: the cart comes back with no wrapping on it.
     */
    public function testAnIdentifierTheShopDoesNotOfferBuysNothing(): void
    {
        $cart = $this->openACheckoutReadyCart();
        $wrapping = $this->createGiftWrapping('gift-box', '3.000000', 'Gift box');

        $crawler = $this->requestThePaymentStep();
        $this->pickTheWrapping($crawler, 999999, expectedChoiceId: (int) $wrapping->getId());

        self::assertNull($this->reread($cart)->getGiftWrappingId());
    }

    public function testTheNoteIsRecordedAsItIsTypedAndFrozenOnTheOrder(): void
    {
        $cart = $this->openACheckoutReadyCart();
        $wrapping = $this->createGiftWrapping('gift-box', '3.000000', 'Gift box');

        $this->pickTheWrapping($this->requestThePaymentStep(), (int) $wrapping->getId());
        $this->writeTheNote($this->requestThePaymentStep(), 'Happy birthday, Mum!');

        self::assertSame('Happy birthday, Mum!', $this->reread($cart)->getGiftMessage());

        $this->client->request('GET', '/checkout/pay');

        $order = OrderQuery::create()->filterByCartId($cart->getId())->findOne($this->getPropelConnection());

        self::assertSame('Happy birthday, Mum!', $order?->getGiftMessage());
    }

    /**
     * The browser counts the characters left as a courtesy; the server is what refuses,
     * and it refuses the note whole rather than storing a cut one.
     */
    public function testANoteOverTheLimitIsRefusedByTheServer(): void
    {
        $cart = $this->openACheckoutReadyCart();
        $wrapping = $this->createGiftWrapping('gift-box', '3.000000', 'Gift box');

        $this->pickTheWrapping($this->requestThePaymentStep(), (int) $wrapping->getId());
        $this->writeTheNote(
            $this->requestThePaymentStep(),
            str_repeat('a', GiftWrapping::MAX_GIFT_MESSAGE_LENGTH + 1),
        );

        self::assertNull($this->reread($cart)->getGiftMessage(), 'Nothing is stored, and nothing is cut.');
        self::assertStringContainsString(
            'characters at most',
            (string) $this->client->getResponse()->getContent(),
            'The buyer must be told why the note was refused.',
        );
    }

    // ------------------------------------------------------------------
    // Harness
    // ------------------------------------------------------------------

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
     * Every radio of the gift block, found by the action it carries rather than by a
     * class name.
     */
    private function wrappingChoices(Crawler $crawler): Crawler
    {
        return $crawler->filter('input[data-live-action-param="chooseWrapping"]');
    }

    /**
     * Picks a wrapping the way the browser does: the action, its argument and the
     * component to send them to are all read off the radio the page rendered.
     *
     * $expectedChoiceId names the radio to send the request through when the identifier
     * being sent is not one the page offers — which is the point of that test.
     */
    private function pickTheWrapping(Crawler $crawler, int $giftWrappingId, ?int $expectedChoiceId = null): void
    {
        $radio = $crawler->filter(\sprintf(
            'input[data-live-action-param="chooseWrapping"][data-live-gift-wrapping-id-param="%d"]',
            $expectedChoiceId ?? $giftWrappingId,
        ));

        self::assertCount(1, $radio, 'There is no choice to click for that wrapping.');

        $this->callLiveAction($radio, 'chooseWrapping', ['giftWrappingId' => $giftWrappingId]);
    }

    /**
     * Types a note the way the browser does: the field is bound to a writable prop, so
     * what travels is the updated prop and not an action argument.
     */
    private function writeTheNote(Crawler $crawler, string $note): void
    {
        $textarea = $crawler->filter('textarea[data-model]');

        self::assertCount(1, $textarea, 'The gift block must offer a field to write the note in.');

        $component = $textarea->ancestors()->filter('[data-live-url-value]')->first();

        self::assertCount(1, $component, 'The field must live inside a live component for what is typed to reach the server.');

        $this->client->request(
            'POST',
            (string) $component->attr('data-live-url-value'),
            ['data' => json_encode([
                'props' => json_decode((string) $component->attr('data-live-props-value'), true, 512, \JSON_THROW_ON_ERROR),
                'updated' => ['giftMessage' => $note],
            ], \JSON_THROW_ON_ERROR)],
            [],
            [
                'HTTP_ACCEPT' => 'application/vnd.live-component+html',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ],
        );

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @param array<string, mixed> $args
     */
    private function callLiveAction(Crawler $node, string $action, array $args): void
    {
        $component = $node->ancestors()->filter('[data-live-url-value]')->first();

        self::assertCount(1, $component, 'The control must live inside a live component for its answer to reach the server.');

        $this->client->request(
            'POST',
            \sprintf('%s/%s', $component->attr('data-live-url-value'), $action),
            ['data' => json_encode([
                'props' => json_decode((string) $component->attr('data-live-props-value'), true, 512, \JSON_THROW_ON_ERROR),
                'updated' => new \stdClass(),
                'args' => $args,
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
            'The action behind the control must answer, or nothing is recorded.',
        );
    }

    private function createGiftWrapping(string $code, string $price, string $title): GiftWrapping
    {
        $taxRule = TaxRuleQuery::create()->findOne($this->getPropelConnection())
            ?? self::fail('The shop must ship with a tax rule.');

        $giftWrapping = (new GiftWrapping())
            ->setCode($code)
            ->setPrice($price)
            ->setTaxRuleId((int) $taxRule->getId())
            ->setActive(1)
            ->setLocale((string) Lang::getDefaultLanguage()->getLocale())
            ->setTitle($title);
        $giftWrapping->save($this->getPropelConnection());

        $this->forgetHydratedGiftWrappings();
        static::getContainer()->get(GiftWrappingProvider::class)->forgetCache();

        return $giftWrapping;
    }

    private function reread(Cart $cart): Cart
    {
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();

        return CartQuery::create()->findPk($cart->getId(), $this->getPropelConnection())
            ?? self::fail('The cart the session was filling is gone.');
    }

    /**
     * The request handlers run in this very process, so a wrapping a test just changed
     * would otherwise be handed back to them as it was read before.
     */
    private function forgetHydratedGiftWrappings(): void
    {
        GiftWrappingTableMap::clearInstancePool();
        GiftWrappingTableMap::clearRelatedInstancePool();
    }

    /**
     * The shop ships with its terms and conditions mandatory, and nothing here is about
     * consents: the order would otherwise be refused for a box these tests never tick.
     */
    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByActive(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setMandatory(0)->save($this->getPropelConnection());
        }

        static::getContainer()->get('Thelia\Domain\Checkout\Service\ConsentProvider')->forgetCache();
    }

    /**
     * A session standing at the payment step with everything but the gift settled. The
     * same way in as the consents suite takes, and for the same reason: walking the
     * delivery step through its own live components would only add ways to fail.
     */
    private function openACheckoutReadyCart(): Cart
    {
        $cart = $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));

        $connection = $this->getPropelConnection();

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
        // fulfil back to the cart page before it ever looks at the gift block.
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

    private function serveTheCountryOf(Address $address): void
    {
        $connection = $this->getPropelConnection();

        $area = (new Area())->setName('Checkout gift wrapping test area');
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
                $event->setMinimumAmount(0);
                $event->setMaximumAmount(0);
                $event->stopPropagation();
            },
            512,
        );
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
