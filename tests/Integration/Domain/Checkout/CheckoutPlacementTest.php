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

namespace Thelia\Tests\Integration\Domain\Checkout;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\Product\VirtualProductOrderHandleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Enum\PaymentActionType;
use Thelia\Domain\Checkout\Exception\CheckoutPlacementInProgressException;
use Thelia\Domain\Checkout\Exception\CheckoutRefusedException;
use Thelia\Domain\Checkout\Service\CheckoutPlacementService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Order\Exception\StockShortageException;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\Consent;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderConsent;
use Thelia\Model\OrderConsentQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * Placing an order with nobody at a browser.
 *
 * Everything the checkout used to read off the session — the currency, the language, the
 * cart, the buyer — is handed over explicitly, and the order goes down the very path the
 * theme takes: ORDER_PAY, the same listener, the same emails, the same payment module
 * call. There is no second way to place an order, only a way to place one without a
 * session, which is what the front API needs.
 */
final class CheckoutPlacementTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->turnMandatoryConsentsOff();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    /**
     * The reference case: a cart paid by cheque. The order exists, it waits for its
     * payment, and the buyer has nothing to be sent to — a cheque is posted, not clicked.
     */
    public function testACartIsPlacedWithoutASessionAndPaidByCheque(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();

        $result = $this->placement()->place($this->requestFor($cart, $customer));

        self::assertFalse($result->alreadyPlaced);
        self::assertNotNull(OrderQuery::create()->findPk($result->orderId, $this->getPropelConnection()));
        self::assertMatchesRegularExpression('/^ORD\d+$/', $result->orderReference);
        self::assertSame(OrderStatus::CODE_NOT_PAID, $result->orderStatusCode);
        self::assertFalse($result->paid);
        self::assertSame(PaymentActionType::None, $result->paymentAction->type);
    }

    /**
     * The currency and the language come from the request, not from a session that is not
     * there, and they are what the order is frozen with.
     */
    public function testTheOrderIsFrozenWithTheCurrencyAndLanguageThatWerePassedIn(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $currency = $this->factory->currency(['code' => 'XPT', 'symbol' => 'XPT', 'rate' => 2.0]);
        // A language of its own, on a locale the catalogue is actually translated in:
        // the point here is which row the order names, not a missing translation.
        $lang = $this->factory->lang(['code' => 'zz', 'title' => 'Placement test language']);

        $result = $this->placement()->place(new CheckoutPlacementRequest($cart, $customer, $currency, $lang));

        $order = OrderQuery::create()->findPk($result->orderId, $this->getPropelConnection());

        self::assertSame($currency->getId(), $order?->getCurrencyId());
        self::assertSame($lang->getId(), $order?->getLangId());
    }

    /**
     * A module that settles the payment on the spot leaves the order paid, and there is
     * nothing for the front to do afterwards: the empty response such a module answers
     * with is not a page to render.
     */
    public function testAFreeOrderIsPlacedPaidAndAsksTheFrontForNothing(): void
    {
        [$cart, $customer] = $this->cartReadyToPay(paymentModuleCode: 'FreeOrder');

        $result = $this->placement()->place($this->requestFor($cart, $customer));

        self::assertSame(OrderStatus::CODE_PAID, $result->orderStatusCode);
        self::assertTrue($result->paid);
        self::assertSame(PaymentActionType::None, $result->paymentAction->type);
    }

    /**
     * A payment module answering with a redirection is the front's instruction to send
     * the buyer to the gateway.
     */
    public function testARedirectingPaymentModuleIsReportedAsARedirection(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $this->answerTheModulePayWith(new RedirectResponse('https://gateway.example.com/pay/1'));

        $result = $this->placement()->place($this->requestFor($cart, $customer));

        self::assertSame(PaymentActionType::Redirect, $result->paymentAction->type);
        self::assertSame('https://gateway.example.com/pay/1', $result->paymentAction->url);
        self::assertNull($result->paymentAction->html);
    }

    /**
     * And a module answering with a page — the self-posting form a gateway requires — is
     * reported as one, with the markup passed on untouched.
     */
    public function testAPaymentModuleAnsweringWithAPageIsReportedAsAFormToRender(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $html = '<form action="https://gateway.example.com/pay"><input name="ref" value="x"></form>';
        $this->answerTheModulePayWith(new Response($html));

        $result = $this->placement()->place($this->requestFor($cart, $customer));

        self::assertSame(PaymentActionType::Form, $result->paymentAction->type);
        self::assertSame($html, $result->paymentAction->html);
        self::assertNull($result->paymentAction->url);
    }

    /**
     * The one a retried request depends on: a cart that already has an order gets that
     * order back, and nothing is created, charged or taken out of stock a second time.
     */
    public function testPlacingTheSameCartTwiceReturnsTheSameOrderAndTakesTheStockOnce(): void
    {
        [$cart, $customer, $product] = $this->cartReadyToPay();
        $this->makeTheOrderManageStockOnCreation();
        $stockBefore = $this->stockOf($product);

        $first = $this->placement()->place($this->requestFor($cart, $customer));
        $second = $this->placement()->place($this->requestFor($cart, $customer));

        self::assertSame($first->orderId, $second->orderId);
        self::assertTrue($second->alreadyPlaced);
        self::assertFalse($first->alreadyPlaced);
        self::assertCount(
            1,
            OrderQuery::create()->filterByCartId($cart->getId())->find($this->getPropelConnection()),
            'A cart that has been ordered must never carry two orders.',
        );
        self::assertSame($stockBefore - 1.0, $this->stockOf($product), 'The stock must move once, not twice.');
    }

    /**
     * The same guarantee when the second request slips in between the check and the write.
     *
     * Reading "this cart has no order yet" and writing the order are two statements, and
     * on two application servers a lock taken in PHP is a lock taken on one of them: the
     * other one reads the same "no order yet" and writes a second. The guard that holds is
     * the one inside the transaction the order is written in, and it is what this pins —
     * the order is inserted here after the applicative check has already answered.
     */
    public function testAnOrderWrittenBetweenTheCheckAndTheWriteDoesNotBecomeASecondOrder(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $orderOfTheOtherRequest = null;

        // ORDER_PAY is answered by the listener that writes the order, at 128. Anything
        // above it happens after this request decided the cart was free, and before a
        // single row of its order exists.
        $this->listen(TheliaEvents::ORDER_PAY, function () use ($cart, $customer, &$orderOfTheOtherRequest): void {
            $orderOfTheOtherRequest ??= $this->placeAConcurrentOrderOn($cart, $customer);
        }, priority: 256);

        $result = $this->placement()->place($this->requestFor($cart, $customer));

        self::assertTrue($result->alreadyPlaced, 'The cart was ordered by the other request; this one has nothing to add.');
        self::assertSame($orderOfTheOtherRequest, $result->orderId);
        self::assertCount(
            1,
            OrderQuery::create()->filterByCartId($cart->getId())->find($this->getPropelConnection()),
            'A cart that has been ordered must never carry two orders.',
        );
    }

    /**
     * The other half of the lock: a request that finds the cart taken and no order behind
     * it is told to wait, and is not handed an order somebody else is in the middle of
     * paying for.
     *
     * The lock is taken here the way the placement takes it — same factory, same name —
     * which is the only way to be sure the two agree on what "this cart is busy" is
     * spelled as.
     */
    public function testACartAnotherRequestIsPlacingIsRefusedRatherThanOrderedTwice(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();

        $lockOfTheOtherRequest = $this->getService(LockFactory::class)
            ->createLock('thelia.checkout.placement.'.$cart->getId());

        self::assertTrue($lockOfTheOtherRequest->acquire(), 'The cart has to be free before the other request takes it.');

        try {
            $this->placement()->place($this->requestFor($cart, $customer));
            self::fail('A cart another request is placing cannot be placed again.');
        } catch (CheckoutPlacementInProgressException) {
            self::assertCount(
                0,
                OrderQuery::create()->filterByCartId($cart->getId())->find($this->getPropelConnection()),
                'Nothing is written while another request holds the cart.',
            );
        } finally {
            $lockOfTheOtherRequest->release();
        }
    }

    /**
     * And once that other request is done, the retry gets its order — with the state of
     * that order read back off the row, since that is all a caller has to go on: the
     * payment is deliberately not raised a second time, so a retry never carries a
     * payment action, whatever the first call answered.
     */
    public function testARetriedPlacementReportsTheStateOfTheOrderAndReplaysNoPayment(): void
    {
        [$cart, $customer] = $this->cartReadyToPay(paymentModuleCode: 'FreeOrder');

        $first = $this->placement()->place($this->requestFor($cart, $customer));
        $second = $this->placement()->place($this->requestFor($cart, $customer));

        self::assertTrue($second->alreadyPlaced);
        self::assertSame($first->orderId, $second->orderId);
        self::assertTrue($second->paid, 'An order already settled must not read as waiting for its payment.');
        self::assertSame(OrderStatus::CODE_PAID, $second->orderStatusCode);
        self::assertSame(PaymentActionType::None, $second->paymentAction->type);
    }

    /**
     * The same retry on an order still waiting for its payment. It says so — `paid: false`
     * and the status of the row — and it says nothing about the redirection the first call
     * answered with: the front has the order to ask about, and the modules are not called
     * twice to find out.
     */
    public function testARetriedPlacementOfAnUnpaidOrderSaysSoAndOffersNoRedirection(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $this->answerTheModulePayWith(new RedirectResponse('https://gateway.example.com/pay/1'));

        $first = $this->placement()->place($this->requestFor($cart, $customer));
        $second = $this->placement()->place($this->requestFor($cart, $customer));

        self::assertSame(PaymentActionType::Redirect, $first->paymentAction->type);
        self::assertTrue($second->alreadyPlaced);
        self::assertFalse($second->paid);
        self::assertSame(OrderStatus::CODE_NOT_PAID, $second->orderStatusCode);
        self::assertSame(PaymentActionType::None, $second->paymentAction->type);
        self::assertNull($second->paymentAction->url);
    }

    /**
     * A payment that did not go through takes the order back, and the buyer must be able
     * to try again with the cart they still have.
     */
    public function testACartWhoseOrderWasCancelledCanBeOrderedAgain(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();

        $first = $this->placement()->place($this->requestFor($cart, $customer));
        $this->cancel($first->orderId);

        $second = $this->placement()->place($this->requestFor($cart, $customer));

        self::assertNotSame($first->orderId, $second->orderId);
        self::assertFalse($second->alreadyPlaced);
    }

    /**
     * Every refusal at once, with the codes a client branches on: the caller has a whole
     * form to correct, not a screen to go back to.
     */
    public function testACartWithNothingSettledIsRefusedWithEveryViolation(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $cart = $this->factory->cart($customer);

        try {
            $this->placement()->place($this->requestFor($cart, $customer));
            self::fail('A cart with nothing settled cannot be ordered.');
        } catch (CheckoutRefusedException $refusal) {
            $codes = array_map(static fn ($violation): string => $violation->code, $refusal->violations);

            self::assertContains(CheckoutViolationCode::CartEmpty->value, $codes);
            self::assertContains(CheckoutViolationCode::PaymentInvalid->value, $codes);
            self::assertGreaterThanOrEqual(2, \count($refusal->violations));
        }

        self::assertCount(0, OrderQuery::create()->filterByCartId($cart->getId())->find($this->getPropelConnection()));
    }

    /**
     * The shop cannot sell what it does not have, and the refusal has to name the line
     * the buyer has to change — "not enough stock" on a cart of ten lines is not an
     * answer anybody can act on.
     *
     * The class is asserted, not only the sentence: it is what tells a shortage apart
     * from every other TheliaProcessException an order can fail with, and what an API
     * answers 409 to rather than 500.
     */
    public function testAnOrderExceedingTheStockIsRefusedNamingTheProduct(): void
    {
        [$cart, $customer, $product] = $this->cartReadyToPay(quantity: 500.0);

        try {
            $this->placement()->place($this->requestFor($cart, $customer));
            self::fail('A cart asking for more than the shop holds cannot be ordered.');
        } catch (StockShortageException $refusal) {
            self::assertSame($product->getRef(), $refusal->productReference);
            self::assertStringContainsString($product->getRef(), $refusal->getMessage());
        }

        self::assertCount(0, OrderQuery::create()->filterByCartId($cart->getId())->find($this->getPropelConnection()));
    }

    /**
     * And the same shortage met by the atomic conditional UPDATE rather than by the
     * read-only pre-check. The two are different code, and only the UPDATE is a guarantee:
     * the pre-check reads, the UPDATE arbitrates, and a shop that sold its last one in
     * between only ever hears from the second.
     *
     * A virtual product whose module keeps a stock is what reaches it from a test: the
     * pre-check walks past every virtual line — their stock usage is decided by an event
     * that needs the order to exist — so the decrement is the only thing left to refuse.
     */
    public function testAShortageFoundByTheAtomicDecrementIsTheSameTypedRefusal(): void
    {
        [$cart, $customer, $product] = $this->cartReadyToPay(virtual: true);
        $cart->setDeliveryModuleId(null)->setAddressDeliveryId(null)->save($this->getPropelConnection());
        $this->makeTheOrderManageStockOnCreation();
        $this->makeTheVirtualProductKeepAStock();
        $this->emptyTheStockOf($product);

        try {
            $this->placement()->place($this->requestFor($cart, $customer));
            self::fail('A cart the shop can no longer serve cannot be ordered.');
        } catch (StockShortageException $refusal) {
            self::assertSame($product->getRef(), $refusal->productReference);
            self::assertStringContainsString($product->getRef(), $refusal->getMessage());
        }

        // What the order row does is not asserted here: the refusal comes from inside the
        // transaction of the facade, which is nested in the one the test runs in, and a
        // nested rollback only marks the outer one — the row is still readable until the
        // test ends. That the shop writes nothing is the business of the two tests above,
        // which refuse before a row exists at all.
    }

    /**
     * A cart with nothing to ship is never asked about a carrier, and the order still
     * needs one: the placement settles it the way the tunnel of a theme does, so that a
     * caller with no delivery screen is not refused for a question it was never asked.
     */
    public function testACartWithNothingToShipIsPlacedWithoutTheBuyerChoosingACarrier(): void
    {
        [$cart, $customer] = $this->cartReadyToPay(virtual: true);
        $cart->setDeliveryModuleId(null)->setAddressDeliveryId(null)->save($this->getPropelConnection());

        $result = $this->placement()->place($this->requestFor($cart, $customer));

        self::assertNotNull(OrderQuery::create()->findPk($result->orderId, $this->getPropelConnection()));
        self::assertSame(
            ModuleQuery::create()->findOneByCode('VirtualProductDelivery')?->getId(),
            $cart->getDeliveryModuleId(),
            'The placement gives the cart the carrier the order cannot be written without.',
        );
    }

    /**
     * The buyer gets their confirmation and the shop gets told, exactly as in the tunnel:
     * both are raised by the listener of ORDER_PAY, which is the one path orders take.
     */
    public function testTheConfirmationAndNotificationEmailsAreSent(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();

        $sent = [];
        foreach ([TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL, TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL] as $eventName) {
            $this->listen($eventName, static function (OrderEvent $event) use (&$sent, $eventName): void {
                $sent[$eventName] = $event->getOrder()->getId();
            }, priority: -512);
        }

        $result = $this->placement()->place($this->requestFor($cart, $customer));

        self::assertSame(
            [
                TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL => $result->orderId,
                TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL => $result->orderId,
            ],
            $sent,
        );
    }

    /**
     * The consents a theme collects screen by screen are handed over in one go, and the
     * order carries the very same proof: one row per consent the shop was asking for,
     * with the wording the answer was given under.
     */
    public function testTheConsentsCarriedByTheRequestAreWrittenOnTheOrder(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $mandatory = $this->turnMandatoryConsentsBackOn();
        $optional = $this->createConsent('newsletter-placement-test', 'Send me the newsletter');

        $result = $this->placement()->place($this->requestFor($cart, $customer, [
            (string) $mandatory->getCode() => true,
            (string) $optional->getCode() => false,
        ]));

        $rows = OrderConsentQuery::create()
            ->filterByOrderId($result->orderId)
            ->orderById()
            ->find($this->getPropelConnection());

        self::assertCount(2, $rows, 'Every consent the shop was asking for belongs on the order, refused ones included.');
        self::assertSame(
            [(string) $mandatory->getCode(), (string) $optional->getCode()],
            array_map(static fn (OrderConsent $row): ?string => $row->getConsentCode(), iterator_to_array($rows, false)),
        );
        self::assertTrue($rows[0]->isAccepted());
        self::assertFalse($rows[1]->isAccepted());
        self::assertSame('Send me the newsletter', $rows[1]->getTitle());
    }

    /**
     * A mandatory consent the request does not accept refuses the placement, and the
     * refusal names the consent rather than saying that something was not agreed to.
     */
    public function testAMandatoryConsentTheRequestRefusesStopsThePlacement(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $mandatory = $this->turnMandatoryConsentsBackOn();

        try {
            $this->placement()->place($this->requestFor($cart, $customer, [(string) $mandatory->getCode() => false]));
            self::fail('A refused mandatory consent must stop the placement.');
        } catch (CheckoutRefusedException $refusal) {
            self::assertSame(CheckoutViolationCode::ConsentMissing->value, $refusal->violations[0]->code);
            self::assertSame((string) $mandatory->getCode(), $refusal->violations[0]->details['consentCode']);
        }

        self::assertCount(0, OrderQuery::create()->filterByCartId($cart->getId())->find($this->getPropelConnection()));
    }

    /**
     * An answer to a consent the shop is not asking for is a refusal like any other, not
     * a failure: the caller gets the same payload, with a code of its own to branch on.
     */
    public function testAnAnswerToAConsentTheShopDoesNotAskForIsARefusal(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();

        try {
            $this->placement()->place($this->requestFor($cart, $customer, ['no-such-consent' => true]));
            self::fail('A consent the shop is not asking for must be refused.');
        } catch (CheckoutRefusedException $refusal) {
            self::assertSame(CheckoutViolationCode::ConsentUnknown->value, $refusal->violations[0]->code);
            self::assertSame('no-such-consent', $refusal->violations[0]->details['consentCode']);
        }

        self::assertCount(0, OrderQuery::create()->filterByCartId($cart->getId())->find($this->getPropelConnection()));
    }

    /**
     * The answers of a refused placement die with it. In a process that serves several
     * placements, a later one that answers nothing must be refused over the consent it
     * did not give — not quietly placed on the boxes somebody ticked for an attempt
     * that ordered nothing.
     */
    public function testARefusedPlacementLeavesNoConsentAnswersToTheNextOne(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $mandatory = $this->turnMandatoryConsentsBackOn();
        $paymentModuleId = $cart->getPaymentModuleId();

        $cart->setPaymentModuleId(null)->save($this->getPropelConnection());

        try {
            $this->placement()->place($this->requestFor($cart, $customer, [(string) $mandatory->getCode() => true]));
            self::fail('A cart with no payment module must not be ordered.');
        } catch (CheckoutRefusedException $refusal) {
            self::assertSame(
                CheckoutViolationCode::PaymentInvalid->value,
                $refusal->violations[0]->code,
                'The consent was answered: the payment is what this refusal is about.',
            );
        }

        $cart->setPaymentModuleId($paymentModuleId)->save($this->getPropelConnection());

        try {
            $this->placement()->place($this->requestFor($cart, $customer));
            self::fail('A placement that answers nothing must not inherit the answers of a refused one.');
        } catch (CheckoutRefusedException $refusal) {
            self::assertSame(CheckoutViolationCode::ConsentMissing->value, $refusal->violations[0]->code);
        }

        self::assertCount(0, OrderQuery::create()->filterByCartId($cart->getId())->find($this->getPropelConnection()));
    }

    /**
     * @param array<string, bool> $consentAnswers
     */
    private function requestFor(Cart $cart, Customer $customer, array $consentAnswers = []): CheckoutPlacementRequest
    {
        return new CheckoutPlacementRequest(
            $cart,
            $customer,
            $this->factory->currency(),
            $this->factory->lang(),
            $consentAnswers,
        );
    }

    private function placement(): CheckoutPlacementService
    {
        return $this->getService(CheckoutPlacementService::class);
    }

    private function cancel(int $orderId): void
    {
        $order = OrderQuery::create()->findPk($orderId, $this->getPropelConnection());
        self::assertInstanceOf(Order::class, $order);
        $order->setCancelled();
    }

    /**
     * Stands in for the request of another application server: an order on this very
     * cart, written while this one is on its way to writing its own.
     */
    private function placeAConcurrentOrderOn(Cart $cart, Customer $customer): int
    {
        $order = $this->factory->order($customer);
        $order->setCartId($cart->getId())->save($this->getPropelConnection());

        return (int) $order->getId();
    }

    /**
     * Stands in for a module selling a virtual product it does hold a stock of — a
     * licence, a seat, a numbered download.
     */
    private function makeTheVirtualProductKeepAStock(): void
    {
        $this->listen(
            TheliaEvents::VIRTUAL_PRODUCT_ORDER_HANDLE,
            static fn (VirtualProductOrderHandleEvent $event) => $event->setUseStock(true),
        );
    }

    private function emptyTheStockOf(Product $product): void
    {
        foreach (ProductSaleElementsQuery::create()->filterByProductId($product->getId())->find($this->getPropelConnection()) as $productSaleElements) {
            $productSaleElements->setQuantity(0)->save($this->getPropelConnection());
        }
    }

    private function stockOf(Product $product): float
    {
        return (float) ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne($this->getPropelConnection())
            ?->getQuantity();
    }

    /**
     * Cheque keeps the stock until the payment is confirmed, which would make "the stock
     * moved once" unobservable. Ask the order to manage it on creation instead, the way a
     * module that takes the money on the spot does.
     */
    private function makeTheOrderManageStockOnCreation(): void
    {
        $this->listen(
            TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'),
            static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true),
        );
    }

    /**
     * @return array{0: Cart, 1: Customer, 2: Product}
     */
    private function cartReadyToPay(
        string $paymentModuleCode = 'Cheque',
        float $quantity = 1.0,
        bool $virtual = false,
    ): array {
        $country = $this->factory->country();

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');
        $paymentModule = ModuleQuery::create()->findOneByCode($paymentModuleCode)
            ?? throw new \RuntimeException("No $paymentModuleCode module installed — run bin/test-prepare.");

        $this->serveCountryWith($deliveryModule, $country);
        $this->answerDeliveryQuoteWith(valid: true);
        $this->answerPaymentValidityWith(valid: true);

        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->factory->address($customer, $country);

        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );

        if ($virtual) {
            $product->setVirtual(1)->save($this->getPropelConnection());
        }

        $cart = $this->factory->cart($customer);
        $this->factory->cartItem($cart, $product, overrides: ['quantity' => $quantity]);

        $cart
            ->setAddressDeliveryId($this->factory->cartAddress(null, $country)->getId())
            ->setAddressInvoiceId($this->factory->cartAddress(null, $country)->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId())
            ->save($this->getPropelConnection());

        return [$cart, $customer, $product];
    }

    private function serveCountryWith(Module $module, Country $country): void
    {
        $area = (new Area())->setName('Placement test area '.uniqid());
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($module->getId())->save($this->getPropelConnection());
    }

    private function answerDeliveryQuoteWith(bool $valid): void
    {
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event) use ($valid): void {
            $event->setValidModule($valid);
            if ($valid) {
                $event->setPostage(new OrderPostage(0.0, 0.0, 'VAT'));
            }
            $event->stopPropagation();
        });
    }

    private function answerPaymentValidityWith(bool $valid): void
    {
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event) use ($valid): void {
            $event->setValidModule($valid);
            $event->stopPropagation();
        });
    }

    /**
     * Stands in for the payment module, so that what the front is told to do can be
     * pinned without a gateway to talk to.
     */
    private function answerTheModulePayWith(Response $response): void
    {
        $this->listen(TheliaEvents::MODULE_PAY, static function ($event) use ($response): void {
            $event->setResponse($response);
            $event->stopPropagation();
        }, priority: 512);
    }

    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            /* @var Consent $consent */
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }

    /**
     * Puts back what setUp() took away, for the tests that are about a shop asking for a
     * consent, and hands back the one they answer.
     */
    private function turnMandatoryConsentsBackOn(): Consent
    {
        $mandatory = null;

        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            /* @var Consent $consent */
            $consent->setActive(1)->save($this->getPropelConnection());
            $mandatory ??= $consent;
        }

        $this->getService(ConsentProvider::class)->forgetCache();

        return $mandatory ?? throw new \RuntimeException('No mandatory consent installed — run bin/test-prepare.');
    }

    private function createConsent(string $code, string $title): Consent
    {
        $consent = (new Consent())
            ->setCode($code)
            ->setMandatory(0)
            ->setActive(1)
            ->setLocale('en_US')
            ->setTitle($title);
        $consent->save($this->getPropelConnection());

        $this->getService(ConsentProvider::class)->forgetCache();

        return $consent;
    }

    private function listen(string $eventName, callable $listener, int $priority = 512): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }
}
