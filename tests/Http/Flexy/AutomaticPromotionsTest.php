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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DomCrawler\Crawler;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Condition\Operators;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Promotion\Coupon\Type\BuyXGetY;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartQuery;
use Thelia\Model\Coupon;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * What a shopper sees on the cart page once the shop runs promotions nobody types a code for:
 * a discount that names itself in the summary, a line the shop put there and the shopper may
 * not touch, and a promotion the shop had to give up on because the gift ran out.
 *
 * The theme decides none of this. The core publishes `discounts`, `unavailable_promotions`,
 * and per line its `is_offered` flag and the `offered_taxed_discount` the promotion owning it
 * grants; these tests pin what the front-office theme makes of them — a label the shopper can
 * read, a gift charged nothing next to the price it would have cost, a warning.
 *
 * The theme ships as its own package on its own release cycle: a theme older than automatic
 * promotions is reported as skipped rather than failed, one marker per feature.
 */
final class AutomaticPromotionsTest extends WebIntegrationTestCase
{
    private const CART_ITEM_TEMPLATE = 'components/Organisms/CartItem/Base.html.twig';

    private const CART_TEMPLATE = 'components/Organisms/Cart/Base.html.twig';

    private const SUMMARY_TEMPLATE = 'components/Organisms/Summary/Checkout.html.twig';

    /** BEM modifier the line wears once the shop, not the shopper, owns it. */
    private const OFFERED_LINE_MARKER = 'CartItem--offered';

    /** Set on the rendered line, and the handle these assertions use. */
    private const OFFERED_LINE_ATTRIBUTE = 'data-cart-item-offered="true"';

    /** Set on the snack bar naming a promotion the shop could not honour. */
    private const UNAVAILABLE_PROMOTION_MARKER = 'data-cart-unavailable-promotion';

    /** Class of a summary row carrying one promotion rather than the lump total. */
    private const DISCOUNT_LINE_MARKER = 'Summary-discountLine';

    /** The quantity stepper, present on a line the shopper owns and on no other. */
    private const QUANTITY_SELECTOR_MARKER = 'CartItem-quantity';

    /** Given to the account these tests sign in with, and typed back into the form. */
    private const PASSWORD = 'Str0ng-p4ssword!';

    public function testAnAutomaticPromotionNamesItselfInTheSummaryOnceTheCartCrossesItsThreshold(): void
    {
        $this->skipUnlessTheThemeCarries(self::SUMMARY_TEMPLATE, self::DISCOUNT_LINE_MARKER);

        $cart = $this->openASessionWithACart();
        $this->fillCart($cart, quantity: 3, unitPrice: '10.000000');

        $this->automaticCoupon([
            'title' => 'Spring bonus',
            'effects' => ['amount' => 5.0],
        ], $this->totalAmountAtLeast($cart, 20.0));

        // The summary lists what the stored discount is made of, and the store only
        // prices the cart on a cart event: make one happen before reading the page.
        $this->makeTheShopReconsiderItsPromotions();

        $content = $this->renderCartPage();

        self::assertStringContainsString(
            self::DISCOUNT_LINE_MARKER,
            $content,
            'A promotion the cart qualifies for must get its own line in the summary.',
        );
        self::assertStringContainsString(
            'Spring bonus',
            $content,
            'The summary must name the promotion, not just print an amount.',
        );
    }

    /**
     * The mirror of the test above, and the reason it proves anything: the same promotion on
     * a cart that stays under its threshold leaves the summary alone.
     */
    public function testAPromotionTheCartStaysUnderIsAbsentFromTheSummary(): void
    {
        $this->skipUnlessTheThemeCarries(self::SUMMARY_TEMPLATE, self::DISCOUNT_LINE_MARKER);

        $cart = $this->openASessionWithACart();
        $this->fillCart($cart, quantity: 1, unitPrice: '10.000000');

        $this->automaticCoupon([
            'title' => 'Big basket bonus',
            'effects' => ['amount' => 5.0],
        ], $this->totalAmountAtLeast($cart, 500.0));

        $content = $this->renderCartPage();

        self::assertStringNotContainsString(
            'Big basket bonus',
            $content,
            'A promotion whose condition is not met must not be advertised in the summary.',
        );
        self::assertStringNotContainsString(
            self::DISCOUNT_LINE_MARKER,
            $content,
            'No promotion applies, so the summary carries no promotion line.',
        );
    }

    /**
     * A line the shop offers is the shop's, not the shopper's: it says so, and it carries
     * neither the quantity stepper nor the delete button. The server refuses both anyway —
     * this is about not offering a control that leads nowhere.
     */
    public function testAnOfferedLineWearsItsBadgeAndCarriesNoQuantitySelector(): void
    {
        $this->skipUnlessTheThemeCarries(self::CART_ITEM_TEMPLATE, self::OFFERED_LINE_MARKER);

        $cart = $this->openASessionWithACart();
        $line = $this->fillCart($cart, quantity: 1, unitPrice: '10.000000', title: 'A gift from the shop');
        $line->setIsOffered(1)->save($this->getPropelConnection());

        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();

        $content = $this->renderCartPage();

        self::assertStringContainsString('A gift from the shop', $content, 'The offered line must be on the page.');
        self::assertStringContainsString(
            self::OFFERED_LINE_ATTRIBUTE,
            $content,
            'An offered line must announce itself as one.',
        );
        self::assertStringNotContainsString(
            self::QUANTITY_SELECTOR_MARKER,
            $content,
            'The only line of this cart is offered: no quantity selector belongs on the page.',
        );
        self::assertStringNotContainsString(
            'live-action-param="remove"',
            $content,
            'An offered line must not offer a way to delete itself.',
        );
        self::assertStringNotContainsString(
            'CartItem-originalPrice',
            $content,
            'Nothing priced this line, so there is no discount to strike a price against.',
        );
    }

    /**
     * The control the test above needs: an ordinary line keeps everything the offered one
     * loses, so the read-only state cannot be passing by rendering nothing at all.
     */
    public function testAnOrdinaryLineKeepsItsQuantitySelectorAndSaysNothingAboutBeingOffered(): void
    {
        $this->skipUnlessTheThemeCarries(self::CART_ITEM_TEMPLATE, self::OFFERED_LINE_MARKER);

        $cart = $this->openASessionWithACart();
        $this->fillCart($cart, quantity: 1, unitPrice: '10.000000', title: 'A product the shopper chose');

        $content = $this->renderCartPage();

        self::assertStringContainsString(
            self::QUANTITY_SELECTOR_MARKER,
            $content,
            'A line the shopper owns keeps its quantity selector.',
        );
        self::assertStringNotContainsString(
            self::OFFERED_LINE_ATTRIBUTE,
            $content,
            'Nothing here is offered: the marker must not leak onto ordinary lines.',
        );
    }

    /**
     * The control the out-of-stock test needs, and the reason it proves anything: the very
     * same promotion, on a gift the shop still has, puts the gift in the cart and gives it
     * away — the line is charged nothing, and the price it would have cost is struck beside
     * the zero.
     */
    public function testAGiftInStockIsPutInTheCartAndChargedNothing(): void
    {
        $this->skipUnlessTheThemeCarries(self::CART_ITEM_TEMPLATE, self::OFFERED_LINE_MARKER);
        $this->skipUnlessTheThemeCarries(self::SUMMARY_TEMPLATE, self::DISCOUNT_LINE_MARKER);

        $cart = $this->openASessionWithACart();
        $trigger = $this->fillCart($cart, quantity: 2, unitPrice: '10.000000', title: 'The product that triggers the gift');

        $this->buyTwoGetOnePromotion(
            $cart,
            $trigger->getProductId(),
            $this->giftProduct('The gift the shop still has', inStock: 5)->getId(),
        );

        $this->makeTheShopReconsiderItsPromotions();

        $crawler = $this->openCartPage();
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString(
            'The gift the shop still has',
            $content,
            'A gift the shop can honour must be put in the cart.',
        );

        $offeredLine = $crawler->filter('.'.self::OFFERED_LINE_MARKER);

        self::assertCount(1, $offeredLine, 'The gift must be the one line of this cart the shop owns.');
        self::assertMatchesRegularExpression(
            '/\b0[.,]00\b/',
            $offeredLine->filter('.CartItem-price')->text(''),
            'A gift given outright is charged nothing.',
        );

        $struck = $offeredLine->filter('.CartItem-originalPrice');

        self::assertCount(1, $struck, 'The price the gift would have cost must be shown, struck.');
        self::assertDoesNotMatchRegularExpression(
            '/\b0[.,]00\b/',
            $struck->text(''),
            'Striking a zero says nothing: the struck figure is what the gift is worth.',
        );

        self::assertStringContainsString(
            'Two bought, one offered',
            $content,
            'The summary must name the promotion that gave the gift away.',
        );
    }

    /**
     * A promotion whose gift is out of stock is not applied, and the shopper is told why
     * rather than left wondering where the announced gift went.
     *
     * The shop works this out while reconciling the offered lines, which it does when the
     * cart changes and not on a page load — so what is pinned here is that the shopper still
     * reads the warning on the page they land on afterwards, and that nothing of the
     * promotion is charged or advertised in the meantime.
     */
    public function testAPromotionWhoseGiftRanOutIsAnnouncedAndTakesNothingOff(): void
    {
        $this->skipUnlessTheThemeCarries(self::CART_TEMPLATE, self::UNAVAILABLE_PROMOTION_MARKER);
        $this->skipUnlessTheThemeCarries(self::SUMMARY_TEMPLATE, self::DISCOUNT_LINE_MARKER);

        $cart = $this->openASessionWithACart();
        $trigger = $this->fillCart($cart, quantity: 2, unitPrice: '10.000000', title: 'The product that triggers the gift');

        $this->buyTwoGetOnePromotion(
            $cart,
            $trigger->getProductId(),
            $this->giftProduct('The gift nobody can have', inStock: 0)->getId(),
        );

        $this->makeTheShopReconsiderItsPromotions();

        $content = $this->renderCartPage();

        self::assertStringNotContainsString(
            'The gift nobody can have',
            $content,
            'An out-of-stock gift must not be put in the cart anyway.',
        );
        self::assertStringNotContainsString(
            self::OFFERED_LINE_ATTRIBUTE,
            $content,
            'No gift could be given, so no line of this cart is offered.',
        );
        self::assertStringContainsString(
            self::UNAVAILABLE_PROMOTION_MARKER,
            $content,
            'The shopper must be told the promotion could not be applied, on the page they land on.',
        );
        self::assertStringContainsString(
            'Two bought, one offered',
            $content,
            'The warning must name the promotion the shop could not honour.',
        );
        self::assertStringNotContainsString(
            self::DISCOUNT_LINE_MARKER,
            $content,
            'A promotion worth nothing has no line to show in the summary.',
        );
    }

    /**
     * A promotion the shopper no longer qualifies for stops naming itself, and the summary
     * goes back to carrying nothing but the totals.
     *
     * The gift line itself is taken back by the core, which reconciles the offered lines when
     * the cart changes; that the row disappears is pinned on the core side. What is pinned
     * here is the theme's half: nothing of the promotion is left on the page once the core
     * stops publishing it.
     */
    public function testAPromotionThatStopsApplyingLeavesNothingBehindOnTheCartPage(): void
    {
        $this->skipUnlessTheThemeCarries(self::SUMMARY_TEMPLATE, self::DISCOUNT_LINE_MARKER);
        $this->skipUnlessTheThemeCarries(self::CART_ITEM_TEMPLATE, self::OFFERED_LINE_MARKER);

        $cart = $this->openASessionWithACart();
        $this->fillCart($cart, quantity: 3, unitPrice: '10.000000', title: 'A product the shopper chose');

        $retired = $this->automaticCoupon([
            'title' => 'A promotion that has been switched off',
            'effects' => ['amount' => 5.0],
        ], $this->totalAmountAtLeast($cart, 20.0));

        $retired->setIsEnabled(false)->save($this->getPropelConnection());

        $content = $this->renderCartPage();

        self::assertStringContainsString(
            'A product the shopper chose',
            $content,
            'Switching a promotion off must leave the rest of the cart alone.',
        );
        self::assertStringNotContainsString(
            'A promotion that has been switched off',
            $content,
            'A promotion that no longer runs must be gone from the summary.',
        );
        self::assertStringNotContainsString(
            self::OFFERED_LINE_ATTRIBUTE,
            $content,
            'No promotion runs, so no line of this cart is offered.',
        );
    }

    /**
     * Opens a real session holding an empty cart — the one the shop itself created for this
     * session, rather than a fixture the session knows nothing about.
     */
    private function openASessionWithACart(): Cart
    {
        $highestCartIdBefore = (int) CartQuery::create()->orderById(Criteria::DESC)->findOne()?->getId();

        $this->client->request('GET', '/checkout/cart');

        $cart = CartQuery::create()
            ->filterById($highestCartIdBefore, Criteria::GREATER_THAN)
            ->orderById(Criteria::DESC)
            ->findOne()
        ;

        if (!$cart instanceof Cart) {
            self::fail('The cart page did not open a cart for this session.');
        }

        return $cart;
    }

    /**
     * Adds one line to the cart, on a product that is titled and in stock: the cart page
     * renders every line through a component whose title is a non-nullable string, and an
     * out-of-stock line renders as unavailable instead of as itself.
     */
    private function fillCart(
        Cart $cart,
        int $quantity,
        string $unitPrice,
        string $title = 'A product in the cart',
    ): CartItem {
        $fixtures = $this->fixtures();

        $product = $fixtures->product(
            $fixtures->category(),
            $fixtures->taxRule(),
            $fixtures->currency(),
            ['title' => $title, 'baseQuantity' => 100],
        );

        $line = $fixtures->cartItem($cart, $product, null, [
            'quantity' => $quantity,
            'price' => $unitPrice,
            'promoPrice' => $unitPrice,
        ]);

        // The request that opened the cart left it in the Propel instance pool with an empty
        // line collection, and this process serves the next request too.
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();

        return $line;
    }

    /**
     * A product the shop can give away, with as many of it in stock as asked for.
     */
    private function giftProduct(string $title, int $inStock): Product
    {
        $fixtures = $this->fixtures();

        return $fixtures->product(
            $fixtures->category(),
            $fixtures->taxRule(),
            $fixtures->currency(),
            ['title' => $title, 'baseQuantity' => $inStock, 'basePrice' => 7.0],
        );
    }

    /**
     * "Buy two of this, get one of that" — automatic, and conditioned on a cart total the
     * caller's cart clears. The condition is what makes the promotion refusable: without
     * one it applies to every cart, and a test of it proves nothing.
     */
    private function buyTwoGetOnePromotion(Cart $cart, int $triggerProductId, int $giftProductId): Coupon
    {
        return $this->automaticCoupon([
            'title' => 'Two bought, one offered',
            'type' => 'thelia.coupon.type.buy_x_get_y',
            'effects' => [
                BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_PRODUCT,
                BuyXGetY::TRIGGER_IDS_FIELD => [$triggerProductId],
                BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
                BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_PRODUCT,
                BuyXGetY::TARGET_PRODUCT_ID_FIELD => $giftProductId,
                BuyXGetY::OFFERED_QUANTITY_FIELD => 1,
                BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_FREE,
                BuyXGetY::DISCOUNT_VALUE_FIELD => 0,
            ],
            // Two lines of ten, taxes or not: the cart clears fifteen and the promotion is
            // one a cart can fail, rather than one that applies to everything.
        ], $this->totalAmountAtLeast($cart, 15.0));
    }

    /**
     * A request that changes the cart, so that the shop reconsiders which promotions apply
     * and puts the offered lines in line with them. It does that when the cart changes and
     * never on a page load, and signing in is the one such change reachable over HTTP from
     * here: the cart page itself mutates through live components, whose signed payloads a
     * test cannot forge.
     */
    private function makeTheShopReconsiderItsPromotions(): void
    {
        $fixtures = $this->fixtures();
        $customer = $fixtures->customer($fixtures->customerTitle(), ['password' => self::PASSWORD]);

        // A shop that confirms email addresses refuses a sign-in on an account that never
        // answered its code, and refuses it by exception rather than by message.
        $customer->setEnable(1)->save($this->getPropelConnection());

        $this->assertPageRenders('/customer/login');

        $signInForm = $this->client
            ->getCrawler()
            ->filter('form')
            ->reduce(static fn (Crawler $form): bool => $form->filter('input[type="password"]')->count() > 0)
        ;

        if (0 === $signInForm->count()) {
            self::markTestSkipped('The installed front-office theme has no sign-in form to submit.');
        }

        $form = $signInForm->form();
        $form['thelia_customer_login[email]'] = $customer->getEmail();
        $form['thelia_customer_login[password]'] = self::PASSWORD;

        $this->client->submit($form);

        self::assertStringNotContainsString(
            '/customer/login',
            (string) $this->client->getResponse()->headers->get('Location'),
            'The sign-in must go through: it is what makes the shop reconsider its promotions.',
        );

        // The requests above left the cart in the Propel instance pool as it was before the
        // promotions were reconciled, and this process serves the page render below too.
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
    }

    /**
     * A promotion the shop applies on its own: no code, enabled, inside its date window.
     */
    private function automaticCoupon(array $overrides, string $serializedConditions = ''): Coupon
    {
        $coupon = $this->fixtures()->coupon($overrides + ['conditions' => $serializedConditions]);

        $coupon
            ->setTriggerMode(Coupon::TRIGGER_MODE_AUTOMATIC)
            ->setCode(null)
            ->save($this->getPropelConnection())
        ;

        return $coupon;
    }

    /**
     * The condition "this cart is worth at least X", in the currency the shop opened the
     * cart with — reading it off the cart rather than assuming the shop trades in euros.
     */
    private function totalAmountAtLeast(Cart $cart, float $amount): string
    {
        /** @var ConditionFactory $factory */
        $factory = $this->getService(ConditionFactory::class);

        $currency = $cart->getCurrency();

        if (null === $currency) {
            self::fail('The cart the shop opened carries no currency.');
        }

        $condition = $factory->build(
            'thelia.condition.match_for_total_amount',
            [
                MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR_OR_EQUAL,
                MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL,
            ],
            [
                MatchForTotalAmount::CART_TOTAL => $amount,
                MatchForTotalAmount::CART_CURRENCY => $currency->getCode(),
            ],
        );

        $collection = new ConditionCollection();
        $collection[] = $condition;

        return $factory->serializeConditionCollection($collection);
    }

    private function renderCartPage(): string
    {
        $this->openCartPage();

        return (string) $this->client->getResponse()->getContent();
    }

    private function openCartPage(): Crawler
    {
        $this->assertPageRenders('/checkout/cart');

        return $this->client->getCrawler();
    }

    /**
     * A theme older than automatic promotions carries none of these markers. Reported as a
     * skip rather than a failure: the core ships with whichever theme version it is given.
     */
    private function skipUnlessTheThemeCarries(string $template, string $marker): void
    {
        $themeRoot = $this->getService(TemplateHelperInterface::class)->getActiveFrontTemplate()->getAbsolutePath();
        $path = $themeRoot.\DIRECTORY_SEPARATOR.str_replace('/', \DIRECTORY_SEPARATOR, $template);

        if (!file_exists($path) || !str_contains((string) file_get_contents($path), $marker)) {
            self::markTestSkipped(
                \sprintf('The installed front-office theme has no "%s" in %s.', $marker, $template),
            );
        }
    }

    /**
     * Built without createFixtureFactory(): that helper pushes a synthetic request when the
     * stack is empty, and it would then be the "main" request of the page render below — the
     * one the session, and therefore the cart, is read from.
     */
    private function fixtures(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }
}
