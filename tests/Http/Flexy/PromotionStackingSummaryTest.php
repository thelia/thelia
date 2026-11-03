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

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Api\Service\DataAccess\AttributeAccessService;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Condition\Operators;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Promotion\Coupon\Service\CouponExclusionWriter;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\Coupon;
use Thelia\Model\Map\CartTableMap;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * What a shopper reads on the cart page when the shop kept one promotion and pushed aside
 * a code they typed: the discount of the promotion kept, and a message naming both.
 *
 * The core decides which promotion wins and publishes the codes it left out; the theme
 * turns them into a message. The theme ships as its own package: one older than the rule
 * is reported as skipped rather than failed.
 */
final class PromotionStackingSummaryTest extends WebIntegrationTestCase
{
    /** Class the theme ships with the message, the witness of a theme that knows the rule. */
    private const THEME_READER_CLASS = 'FlexyBundle\Service\ExcludedPromotionsReader';

    /** Set on the snack bar naming a code left out, valued with that code. */
    private const EXCLUDED_MARKER = 'data-summary-excluded-promotion';

    /** Class of a summary row carrying one promotion rather than the lump total. */
    private const DISCOUNT_LINE_MARKER = 'Summary-discountLine';

    private const PASSWORD = 'Str0ng-p4ssword!';

    /** @var list<string>|null */
    private ?array $typedCodes = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typedCodes = null;

        if (!class_exists(self::THEME_READER_CLASS)) {
            self::markTestSkipped('The installed front-office theme predates the message on a code left out.');
        }

        AttributeAccessService::clearCache();

        // The browser keeps its own session, not the one of the container: what the buyer
        // typed is put into the session of each request on its way in.
        $this->getService(EventDispatcherInterface::class)->addListener(
            KernelEvents::REQUEST,
            function (RequestEvent $event): void {
                if (null === $this->typedCodes || !$event->getRequest()->hasSession()) {
                    return;
                }

                $session = $event->getRequest()->getSession();

                if ($session instanceof Session) {
                    $session->setConsumedCoupons($this->typedCodes);
                }
            },
            -1024,
        );
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function typedOrders(): iterable
    {
        yield 'the promotion kept first' => [['ALPHA', 'BRAVO']];
        yield 'the promotion kept last' => [['BRAVO', 'ALPHA']];
    }

    /**
     * @param list<string> $typedOrder
     */
    #[DataProvider('typedOrders')]
    public function testTwoIncompatibleCodesShowTheDiscountOfTheFirstOnlyAndNameBothInTheMessage(array $typedOrder): void
    {
        $cart = $this->openASessionWithACart();
        $this->fillCart($cart);

        $alpha = $this->promotion('ALPHA', 'Alpha deal', ['priority' => 10, 'effects' => ['amount' => 5.0]]);
        $bravo = $this->promotion('BRAVO', 'Bravo deal', ['priority' => 20, 'effects' => ['amount' => 7.0]]);
        $this->getService(CouponExclusionWriter::class)->replace($alpha->getId(), [$bravo->getId()]);

        $this->type(...$typedOrder);
        $crawler = $this->openCartPage();

        $message = $crawler->filter('['.self::EXCLUDED_MARKER.'="BRAVO"]');
        self::assertCount(1, $message, 'The code left out must be announced once.');
        self::assertStringContainsString('Bravo deal', $message->text(''), 'The message names the code left out.');
        self::assertStringContainsString('Alpha deal', $message->text(''), 'The message names the promotion kept.');
        self::assertCount(0, $crawler->filter('['.self::EXCLUDED_MARKER.'="ALPHA"]'), 'The promotion kept is not announced as left out.');

        $discountLines = $crawler->filter('.'.self::DISCOUNT_LINE_MARKER);
        self::assertCount(1, $discountLines, 'Only the promotion kept takes something off.');
        self::assertStringContainsString('Alpha deal', $discountLines->text(''));
        self::assertStringNotContainsString('Bravo deal', $discountLines->text(''));
    }

    public function testACodeLeftOutBecauseAnotherPromotionStoppedTheRestIsNamedWithTheStopper(): void
    {
        $cart = $this->openASessionWithACart();
        $this->fillCart($cart);

        $this->promotion('STOPPER', 'Stopper deal', ['priority' => 1, 'stopProcessing' => true]);
        $this->promotion('LATER', 'Later deal', ['priority' => 2]);

        $this->type('LATER', 'STOPPER');
        $crawler = $this->openCartPage();

        $message = $crawler->filter('['.self::EXCLUDED_MARKER.'="LATER"]');
        self::assertCount(1, $message, 'The code stopped by the first one must be announced.');
        self::assertStringContainsString('Later deal', $message->text(''));
        self::assertStringContainsString('Stopper deal', $message->text(''));
        self::assertCount(0, $crawler->filter('['.self::EXCLUDED_MARKER.'="STOPPER"]'));
    }

    public function testAnAutomaticPromotionPushedAsideIsNeverNamedOnThePage(): void
    {
        $cart = $this->openASessionWithACart();
        $this->fillCart($cart);

        $this->promotion('STOPPER', 'Stopper deal', ['priority' => 1, 'stopProcessing' => true]);
        $automatic = $this->promotion('AUTO', 'Quiet automatic deal', ['priority' => 2]);
        // An automatic promotion may carry a code too: the buyer still did not ask for it.
        $automatic->setTriggerMode(Coupon::TRIGGER_MODE_AUTOMATIC)->save($this->getPropelConnection());

        $this->type('STOPPER');
        $crawler = $this->openCartPage();
        $content = (string) $this->client->getResponse()->getContent();

        // The control: the rule did push the automatic promotion aside, the page simply does not say so.
        $selection = $this->getService(CouponManager::class)->getSelection();
        self::assertSame(['STOPPER'], array_map(static fn ($kept): string => (string) $kept->getCode(), $selection->kept), 'The code typed must be kept.');
        self::assertCount(0, $crawler->filter('['.self::EXCLUDED_MARKER.']'), 'No code was left out, so no message.');
        self::assertStringNotContainsString('Quiet automatic deal', $content, 'An automatic promotion pushed aside is never named.');
    }

    public function testAPromotionTitleCarryingMarkupComesOutEscaped(): void
    {
        $cart = $this->openASessionWithACart();
        $this->fillCart($cart);

        $this->promotion('STOPPER', 'Stopper <script>alert(1)</script>', ['priority' => 1, 'stopProcessing' => true]);
        $this->promotion('LATER', 'Later <script>alert(2)</script>', ['priority' => 2]);

        $this->type('LATER', 'STOPPER');
        $crawler = $this->openCartPage();
        $content = (string) $this->client->getResponse()->getContent();

        self::assertCount(1, $crawler->filter('['.self::EXCLUDED_MARKER.'="LATER"]'), 'The message must be on the page for this test to prove anything.');
        self::assertStringNotContainsString('<script>alert(1)</script>', $content, 'A title must never reach the page as markup.');
        self::assertStringNotContainsString('<script>alert(2)</script>', $content, 'A title must never reach the page as markup.');
        self::assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $content, 'The title is shown, as text.');
    }

    public function testAnIncompatibleCodeWhoseConditionsAreNotMetIsNotAnnounced(): void
    {
        $cart = $this->openASessionWithACart();
        $this->fillCart($cart);

        $kept = $this->promotion('KEPT', 'Kept deal', ['priority' => 1]);
        $unmet = $this->promotion('UNMET', 'Unmet deal', ['priority' => 2, 'conditions' => $this->totalAtLeast($cart, 100000.0)]);
        $this->getService(CouponExclusionWriter::class)->replace($kept->getId(), [$unmet->getId()]);

        $this->type('UNMET', 'KEPT');
        $crawler = $this->openCartPage();

        self::assertCount(0, $crawler->filter('['.self::EXCLUDED_MARKER.']'), 'A code that would not apply anyway is not announced.');
        self::assertCount(1, $crawler->filter('.'.self::DISCOUNT_LINE_MARKER), 'The control: the promotion kept still takes its discount.');
    }

    /**
     * What the buyer typed, as the session keeps it, and the shop made to reconsider it.
     */
    private function type(string ...$codes): void
    {
        $this->typedCodes = array_values($codes);

        $this->getService(CouponManager::class)->invalidateCurrentCoupons();
        AttributeAccessService::clearCache();

        $this->makeTheShopReconsiderItsPromotions();
    }

    /**
     * The shop prices the cart on a cart event and never on a page load; signing in is the
     * one such event reachable over HTTP from here (the cart page mutates through live
     * components, whose signed payloads a test cannot forge).
     */
    private function makeTheShopReconsiderItsPromotions(): void
    {
        $fixtures = $this->fixtures();
        $customer = $fixtures->customer($fixtures->customerTitle(), ['password' => self::PASSWORD]);
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

        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
        AttributeAccessService::clearCache();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function promotion(string $code, string $title, array $overrides = []): Coupon
    {
        return $this->fixtures()->coupon($overrides + [
            'code' => $code,
            'title' => $title,
            'isCumulative' => true,
            'effects' => ['amount' => 5.0],
            'conditions' => $this->everyone(),
        ]);
    }

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

    private function forgetTheCart(): void
    {
        $this->typedCodes = null;
        $session = static::getContainer()->get(Session::class);
        \assert($session instanceof Session);
        $session->setSessionCart(null);
        $session->clear();
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
    }

    private function fillCart(Cart $cart): void
    {
        $fixtures = $this->fixtures();

        $product = $fixtures->product(
            $fixtures->category(),
            $fixtures->taxRule(),
            $fixtures->currency(),
            ['title' => 'A product in the cart', 'baseQuantity' => 100],
        );

        $fixtures->cartItem($cart, $product, null, [
            'quantity' => 3,
            'price' => '10.000000',
            'promoPrice' => '10.000000',
        ]);

        // The request that opened the cart left it in the Propel instance pool with an empty
        // line collection, and this process serves the next request too.
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
    }

    private function everyone(): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(MatchForEveryone::class);

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }

    private function totalAtLeast(Cart $cart, float $amount): string
    {
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

    private function openCartPage(): Crawler
    {
        $this->assertPageRenders('/checkout/cart');

        return $this->client->getCrawler();
    }

    /**
     * Built without createFixtureFactory(): that helper pushes a synthetic request when the
     * stack is empty, and it would then be the "main" request of the page render.
     */
    private function fixtures(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }
}
