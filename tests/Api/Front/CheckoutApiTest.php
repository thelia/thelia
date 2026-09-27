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

namespace Thelia\Tests\Api\Front;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\Address;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\Consent;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Customer;
use Thelia\Model\Map\ConsentTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderConsent;
use Thelia\Model\OrderConsentQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ApiTestCase;

/**
 * The order tunnel of an account, driven over the front API.
 *
 * Six operations under `/front/account/checkout/{cartId}`, every one of them guarded
 * twice: by the firewall that demands an account, and by the ownership check the
 * processors make on the cart named in the url. What that second barrier answers is the
 * point of half of this file — a cart that is not the caller's has to be indistinguishable
 * from a cart that does not exist, body included, or the endpoints become a way of
 * counting the carts of the shop.
 */
final class CheckoutApiTest extends ApiTestCase
{
    private const POSTAGE_AMOUNT = 7.5;
    private const POSTAGE_TAX = 1.5;

    /**
     * Stands in for everything the core and its modules raise as a TheliaProcessException
     * while an order is being written. It is deliberately recognisable, so that a test can
     * state that it did not come back to the caller.
     */
    private const INTERNAL_FAILURE_SENTENCE = 'Internal wiring of the shop is broken';

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
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
     * The reference run: a filled cart, the four choices posted one after the other, a
     * verdict of "ready", and an order paid by cheque the account can read back.
     */
    public function testAnAuthenticatedCustomerWalksTheWholeCheckoutAndGetsAnOrder(): void
    {
        $checkout = $this->readyCheckout();
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();

        $delivery = $this->jsonRequest('POST', $this->url($cartId, 'delivery_address'), ['addressId' => $checkout['address']->getId()], token: $token);
        self::assertJsonResponseSuccessful($delivery);

        $invoice = $this->jsonRequest('POST', $this->url($cartId, 'invoice_address'), ['addressId' => $checkout['address']->getId()], token: $token);
        self::assertJsonResponseSuccessful($invoice);

        $deliveryModule = $this->jsonRequest('POST', $this->url($cartId, 'delivery_module'), ['deliveryModuleId' => $checkout['deliveryModule']->getId()], token: $token);
        self::assertJsonResponseSuccessful($deliveryModule);

        $paymentModule = $this->jsonRequest('POST', $this->url($cartId, 'payment_module'), ['paymentModuleId' => $checkout['paymentModule']->getId()], token: $token);
        self::assertJsonResponseSuccessful($paymentModule);

        // The postage the chosen carrier quoted travels with every answer from the
        // moment it is known: a front showing a checkout has to be able to show what the
        // delivery costs without asking for the cart again.
        foreach (['delivery module' => $deliveryModule, 'payment module' => $paymentModule] as $step => $response) {
            $cart = $this->decode($response);
            self::assertSame(self::POSTAGE_AMOUNT, (float) $cart['postage'], \sprintf('The answer to the %s step must carry the requoted postage.', $step));
            self::assertSame(self::POSTAGE_TAX, (float) $cart['postageTax']);
        }

        // And the totals with it. They are not columns of the cart: they are computed at
        // serialization, and only for a payload that declares them — which the checkout
        // answers do, being the very payload a buyer reads a price off.
        $cart = $this->decode($paymentModule);
        foreach (['totalWithoutTax', 'taxes', 'total'] as $amount) {
            self::assertArrayHasKey($amount, $cart);
            self::assertNotNull($cart[$amount], \sprintf('The checkout answer must carry the computed %s.', $amount));
        }
        self::assertGreaterThan(0.0, (float) $cart['total']);

        $validation = $this->jsonRequest('GET', $this->url($cartId, 'validation'), token: $token);
        self::assertJsonResponseSuccessful($validation);
        self::assertSame(['ready' => true, 'violations' => []], $this->decode($validation));

        $placement = $this->jsonRequest('POST', $this->url($cartId, 'place'), token: $token);
        self::assertJsonResponseSuccessful($placement);
        $placed = $this->decode($placement);

        self::assertFalse($placed['alreadyPlaced']);
        self::assertFalse($placed['paid']);
        self::assertSame(OrderStatus::CODE_NOT_PAID, $placed['orderStatusCode']);
        self::assertSame('none', $placed['paymentAction']['type']);
        self::assertNull($placed['paymentAction']['url']);
        self::assertNull($placed['paymentAction']['html']);
        self::assertMatchesRegularExpression('/^ORD\d+$/', $placed['orderReference']);

        $order = $this->jsonRequest('GET', '/api/front/account/orders/'.$placed['orderId'], token: $token);
        self::assertJsonResponseSuccessful($order);
        self::assertSame($placed['orderReference'], $this->decode($order)['ref']);
    }

    public function testAPaymentModuleJudgingTheCartItPricesAcceptsAPlacementWithoutSession(): void
    {
        $checkout = $this->readyCheckout(judgePaymentForReal: true);
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();

        $this->walkTheSelections($cartId, $checkout, $token);

        $validation = $this->jsonRequest('GET', $this->url($cartId, 'validation'), token: $token);
        self::assertJsonResponseSuccessful($validation);
        self::assertSame(['ready' => true, 'violations' => []], $this->decode($validation));

        $placement = $this->jsonRequest('POST', $this->url($cartId, 'place'), token: $token);
        self::assertJsonResponseSuccessful($placement);
        $placed = $this->decode($placement);

        self::assertFalse($placed['paid']);
        self::assertSame(OrderStatus::CODE_NOT_PAID, $placed['orderStatusCode']);
        self::assertSame($checkout['paymentModule']->getId(), OrderQuery::create()->findPk($placed['orderId'])?->getPaymentModuleId());
    }

    public function testTheAmountAPaymentModuleIsAskedToCollectIsTheTotalOfTheOrderItPays(): void
    {
        $checkout = $this->readyCheckout(judgePaymentForReal: true);
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();

        $this->walkTheSelections($cartId, $checkout, $token);

        $amountSeenWhilePaying = null;
        $this->listen(TheliaEvents::MODULE_PAY, static function (OrderPaymentEvent $event) use (&$amountSeenWhilePaying): void {
            $module = ModuleQuery::create()->findPk($event->getOrder()->getPaymentModuleId())?->getPaymentModuleInstance(static::getContainer());
            self::assertInstanceOf(BaseModule::class, $module);
            $amountSeenWhilePaying = $module->getCurrentOrderTotalAmount();
        });

        $placement = $this->jsonRequest('POST', $this->url($cartId, 'place'), token: $token);
        self::assertJsonResponseSuccessful($placement);

        $order = OrderQuery::create()->findPk($this->decode($placement)['orderId']);
        self::assertNotNull($order);
        self::assertGreaterThan(self::POSTAGE_AMOUNT, $order->getTotalAmount());
        self::assertEqualsWithDelta($order->getTotalAmount(), $amountSeenWhilePaying, 0.001);
    }

    public function testTheFreeOrderModuleRefusesACartThatCostsSomething(): void
    {
        $checkout = $this->readyCheckout(judgePaymentForReal: true);
        $checkout['paymentModule'] = ModuleQuery::create()->findOneByCode('FreeOrder')
            ?? throw new \RuntimeException('No FreeOrder module installed — run bin/test-prepare.');
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();

        $this->walkTheSelections($cartId, $checkout, $token);

        $placement = $this->jsonRequest('POST', $this->url($cartId, 'place'), token: $token);

        self::assertSame(422, $placement->getStatusCode());
        self::assertContains(CheckoutViolationCode::PaymentInvalid->value, array_column($this->decode($placement)['violations'], 'code'));
        self::assertCount(0, OrderQuery::create()->filterByCartId($cartId)->find($this->getPropelConnection()));
    }

    public function testAPaymentModuleJudgingTheCartItPricesStillRefusesACartWorthNothing(): void
    {
        $checkout = $this->readyCheckout(judgePaymentForReal: true);
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();

        $this->walkTheSelections($cartId, $checkout, $token);

        foreach ($checkout['cart']->getCartItems() as $cartItem) {
            $cartItem->setPrice('0')->setPromoPrice('0')->save($this->getPropelConnection());
        }
        $checkout['cart']->setPostage('0')->setPostageTax('0')->save($this->getPropelConnection());

        $placement = $this->jsonRequest('POST', $this->url($cartId, 'place'), token: $token);

        self::assertSame(422, $placement->getStatusCode());
        self::assertContains(CheckoutViolationCode::PaymentInvalid->value, array_column($this->decode($placement)['violations'], 'code'));
        self::assertCount(0, OrderQuery::create()->filterByCartId($cartId)->find($this->getPropelConnection()));
    }

    /**
     * A client submitting a whole checkout is told everything that is wrong with it at
     * once, with the codes it branches on — not one missing field per round trip.
     */
    public function testValidationReportsEveryViolationAtOnceWithItsStableCodes(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', $this->url($cart->getId(), 'validation'), token: $token);

        self::assertJsonResponseSuccessful($response);
        $verdict = $this->decode($response);

        self::assertFalse($verdict['ready']);
        self::assertGreaterThanOrEqual(2, \count($verdict['violations']));

        $codes = array_column($verdict['violations'], 'code');
        self::assertContains(CheckoutViolationCode::CartEmpty->value, $codes);
        self::assertContains(CheckoutViolationCode::PaymentInvalid->value, $codes);

        foreach ($verdict['violations'] as $violation) {
            self::assertArrayHasKey('stepCode', $violation);
            self::assertArrayHasKey('message', $violation);
            self::assertArrayHasKey('details', $violation);
        }
    }

    /**
     * The same refusal, met while placing rather than while asking: same shape, same
     * codes, and 422 rather than an order.
     */
    public function testPlacingACartWithNothingSettledIsRefusedWithEveryViolation(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('POST', $this->url($cart->getId(), 'place'), token: $token);

        self::assertSame(422, $response->getStatusCode());
        $refusal = $this->decode($response);

        self::assertFalse($refusal['ready']);
        self::assertContains(CheckoutViolationCode::CartEmpty->value, array_column($refusal['violations'], 'code'));
        self::assertCount(0, OrderQuery::create()->filterByCartId($cart->getId())->find($this->getPropelConnection()));
    }

    /**
     * The one refusal that is a conflict with the state of the shop rather than with the
     * body: the same request a minute earlier would have gone through. It names the
     * product, because "not enough stock" about a cart of ten lines is not an answer a
     * client can act on, and the reference is not an internal detail — it is printed on
     * the catalogue page the buyer came from.
     */
    public function testPlacingACartTheShopCanNoLongerServeIsAConflictNamingTheProduct(): void
    {
        $checkout = $this->readyCheckout();
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();
        $this->walkTheSelections($cartId, $checkout, $token);
        $this->emptyTheStockOf($checkout['product']);

        $response = $this->jsonRequest('POST', $this->url($cartId, 'place'), token: $token);

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString(
            (string) $checkout['product']->getRef(),
            (string) $response->getContent(),
            'A shortage must name the line the buyer has to change.',
        );
        self::assertCount(0, OrderQuery::create()->filterByCartId($cartId)->find($this->getPropelConnection()));
    }

    /**
     * And everything else that goes wrong inside the placement is not a conflict.
     *
     * `TheliaProcessException` is the whole family the core and its modules raise, from a
     * missing customer id to a module that answered nothing: mapping it to 409 told a
     * client to retry a request that will never work, and handed it a sentence written
     * for a log. It stays a 500, and the sentence stays in the log.
     */
    public function testAFailureInsideThePlacementIsNotReportedAsAConflictAndSaysNothingInternal(): void
    {
        $checkout = $this->readyCheckout();
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();
        $this->walkTheSelections($cartId, $checkout, $token);

        $this->listen(TheliaEvents::ORDER_PAY, static function (): void {
            throw new TheliaProcessException(self::INTERNAL_FAILURE_SENTENCE);
        }, priority: 1024);

        $response = $this->jsonRequest('POST', $this->url($cartId, 'place'), token: $token);

        self::assertSame(500, $response->getStatusCode(), 'A broken shop is not a conflict the caller can resolve by retrying.');
        self::assertStringNotContainsString(
            self::INTERNAL_FAILURE_SENTENCE,
            (string) $response->getContent(),
            'An internal message must never reach the caller.',
        );
        self::assertCount(0, OrderQuery::create()->filterByCartId($cartId)->find($this->getPropelConnection()));
    }

    /**
     * A retried request — a double click, a client that lost the answer — gets the order
     * it already has, and the shop never writes a second one.
     */
    public function testPlacingTheSameCartTwiceAnswersWithTheOrderItAlreadyHas(): void
    {
        $checkout = $this->readyCheckout();
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();
        $this->walkTheSelections($cartId, $checkout, $token);

        $first = $this->decode($this->jsonRequest('POST', $this->url($cartId, 'place'), token: $token));
        $secondResponse = $this->jsonRequest('POST', $this->url($cartId, 'place'), token: $token);

        self::assertSame(200, $secondResponse->getStatusCode());
        $second = $this->decode($secondResponse);

        self::assertFalse($first['alreadyPlaced']);
        self::assertTrue($second['alreadyPlaced']);
        self::assertSame($first['orderId'], $second['orderId']);
        self::assertCount(
            1,
            OrderQuery::create()->filterByCartId($cartId)->find($this->getPropelConnection()),
            'A cart that has been ordered must never carry two orders.',
        );
    }

    /**
     * The heart of the ownership barrier: every operation answers the cart of somebody
     * else exactly as it answers a cart that was never created — same status, same body.
     */
    public function testEveryOperationAnswersTheCartOfAnotherCustomerAsItAnswersAnUnknownCart(): void
    {
        $checkout = $this->readyCheckout();
        $factory = $this->createFixtureFactory();
        $attacker = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $token = $this->authenticateAsCustomer($attacker);

        $victimCartId = $checkout['cart']->getId();
        $unknownCartId = $this->anIdNoCartHas();

        foreach ($this->everyOperation($checkout) as $label => [$method, $path, $payload]) {
            $onTheVictim = $this->jsonRequest($method, $this->url($victimCartId, $path), $payload, token: $token);
            $onNothing = $this->jsonRequest($method, $this->url($unknownCartId, $path), $payload, token: $token);

            self::assertSame(404, $onTheVictim->getStatusCode(), \sprintf('"%s" must not act on the cart of another account.', $label));
            self::assertSame(404, $onNothing->getStatusCode());
            self::assertSame(
                $this->comparableBody($onNothing),
                $this->comparableBody($onTheVictim),
                \sprintf('"%s" must answer somebody else\'s cart exactly as it answers a cart that does not exist.', $label),
            );
        }

        self::assertCount(0, OrderQuery::create()->filterByCartId($victimCartId)->find($this->getPropelConnection()));
    }

    /**
     * One layer down: the caller owns the cart, and names an address that is not theirs.
     * It is refused, and refused the same way an address that does not exist is.
     */
    public function testAnAddressOfAnotherAccountIsRefusedWithoutSayingWhetherItExists(): void
    {
        $checkout = $this->readyCheckout();
        $factory = $this->createFixtureFactory();
        $stranger = $factory->customer($factory->customerTitle());
        $addressOfTheStranger = $factory->address($stranger, $checkout['country']);

        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();

        foreach (['delivery_address', 'invoice_address'] as $step) {
            $stolen = $this->jsonRequest('POST', $this->url($cartId, $step), ['addressId' => $addressOfTheStranger->getId()], token: $token);
            $imaginary = $this->jsonRequest('POST', $this->url($cartId, $step), ['addressId' => $this->anIdNoAddressHas()], token: $token);

            self::assertSame(404, $stolen->getStatusCode());
            self::assertSame($this->comparableBody($imaginary), $this->comparableBody($stolen));
        }

        $checkout['cart']->reload(true);
        self::assertNull($checkout['cart']->getAddressDeliveryId(), 'A refused address must not have been written on the cart.');
        self::assertNull($checkout['cart']->getAddressInvoiceId());
    }

    public function testEveryOperationRefusesACallerWithoutAnAccount(): void
    {
        $checkout = $this->readyCheckout();
        $cartId = $checkout['cart']->getId();

        foreach ($this->everyOperation($checkout) as $label => [$method, $path, $payload]) {
            $response = $this->jsonRequest($method, $this->url($cartId, $path), $payload);

            self::assertSame(401, $response->getStatusCode(), \sprintf('"%s" must not be reachable without an account.', $label));
        }
    }

    /**
     * A cart with nothing to ship is never asked who carries it — the delivery step is
     * left out of its tunnel — and the order still needs a carrier. The placement settles
     * it, so the buyer is not refused for a question they were never asked.
     */
    public function testACartWithNothingToShipIsPlacedWithoutChoosingACarrier(): void
    {
        $checkout = $this->readyCheckout(virtual: true);
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();

        $this->jsonRequest('POST', $this->url($cartId, 'invoice_address'), ['addressId' => $checkout['address']->getId()], token: $token);
        $this->jsonRequest('POST', $this->url($cartId, 'payment_module'), ['paymentModuleId' => $checkout['paymentModule']->getId()], token: $token);

        $response = $this->jsonRequest('POST', $this->url($cartId, 'place'), token: $token);

        self::assertJsonResponseSuccessful($response);
        $placed = $this->decode($response);
        self::assertNotNull(OrderQuery::create()->findPk($placed['orderId'], $this->getPropelConnection()));
        $checkout['cart']->reload(true);
        self::assertSame(
            ModuleQuery::create()->findOneByCode('VirtualProductDelivery')?->getId(),
            $checkout['cart']->getDeliveryModuleId(),
            'The placement gives the cart the carrier the order cannot be written without.',
        );
    }

    /**
     * Posting a choice the cart already holds costs nothing: re-quoting the postage means
     * asking every carrier module for a price, sometimes over the network, and a client
     * replaying a whole checkout posts each step whether or not it moved.
     *
     * Two places used to ask. The selection itself, which is short-circuited when the cart
     * already holds the choice — and the serialization of the answer, which estimated a
     * delivery over every carrier that could serve the shop's default country, on every
     * single response, for a cart whose carrier the buyer had already picked and whose
     * postage was already written. That second one is why the carriers are served the
     * default country here: without it no module is eligible, nothing is asked, and the
     * count says nothing.
     */
    public function testPostingAChoiceTheCartAlreadyHoldsDoesNotRequoteThePostage(): void
    {
        $checkout = $this->readyCheckout();
        $this->serveCountryWith($checkout['deliveryModule'], Country::getDefaultCountry());
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();
        $this->walkTheSelections($cartId, $checkout, $token);

        $quotesBefore = $this->countPostageQuotes();

        $response = $this->jsonRequest('POST', $this->url($cartId, 'delivery_module'), ['deliveryModuleId' => $checkout['deliveryModule']->getId()], token: $token);
        $addressAgain = $this->jsonRequest('POST', $this->url($cartId, 'delivery_address'), ['addressId' => $checkout['address']->getId()], token: $token);

        self::assertJsonResponseSuccessful($response);
        self::assertJsonResponseSuccessful($addressAgain);
        self::assertSame(
            $quotesBefore,
            $this->countPostageQuotes(),
            'Re-posting the selection the cart already holds must not ask the carriers for a price again.',
        );
        self::assertSame(self::POSTAGE_AMOUNT, (float) $this->decode($response)['postage']);

        // And the answer still states a delivery cost: it is the one the chosen carrier
        // quoted and the cart was written with, not an estimate over the carriers the
        // buyer did not pick.
        self::assertSame(self::POSTAGE_AMOUNT, (float) $this->decode($response)['delivery']);
        self::assertSame(self::POSTAGE_TAX, (float) $this->decode($response)['deliveryTax']);
    }

    /**
     * Correcting an address is not the same choice, even under the same id: the cart
     * holds a copy of the address, and a copy that no longer says what the address says
     * would have the shop quote a delivery to a place the buyer has moved away from.
     */
    public function testAnAddressEditedUnderTheSameIdIsCopiedOntoTheCartAgain(): void
    {
        $checkout = $this->readyCheckout();
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();

        $this->jsonRequest('POST', $this->url($cartId, 'delivery_address'), ['addressId' => $checkout['address']->getId()], token: $token);

        $checkout['address']->setCity('Elsewhere')->setZipcode('99999')->save($this->getPropelConnection());

        $this->jsonRequest('POST', $this->url($cartId, 'delivery_address'), ['addressId' => $checkout['address']->getId()], token: $token);

        $checkout['cart']->reload(true);
        self::assertSame(
            'Elsewhere',
            $checkout['cart']->getCartAddressRelatedByAddressDeliveryId()?->getCity(),
            'The cart must ship to the address as it now reads.',
        );
    }

    /**
     * What a buyer agrees to travels in the body of the placement, and ends up on the
     * order exactly as it does when a theme collects it.
     *
     * There is no operation to tick a box with: the answers of a theme live in the
     * session until there is an order to write them on, and the API firewall is
     * stateless — so the one request that has an order to write them on is the one that
     * carries them. A refused consent is an answer too, and it is recorded as such.
     */
    public function testConsentsAnsweredInThePlacementBodyLetTheOrderThroughAndAreFrozenOnIt(): void
    {
        $checkout = $this->readyCheckout();
        $this->turnMandatoryConsentsBackOn();
        $mandatory = $this->aMandatoryConsent();
        $this->createConsent('newsletter-api-test', 'Send me the newsletter');
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();
        $this->walkTheSelections($cartId, $checkout, $token);

        $placement = $this->jsonRequest('POST', $this->url($cartId, 'place'), [
            'consents' => [
                ['code' => $mandatory->getCode(), 'accepted' => true],
                ['code' => 'newsletter-api-test', 'accepted' => false],
            ],
        ], token: $token);

        self::assertJsonResponseSuccessful($placement);
        $placed = $this->decode($placement);
        self::assertFalse($placed['alreadyPlaced']);

        $order = OrderQuery::create()->findPk($placed['orderId'], $this->getPropelConnection());
        self::assertNotNull($order);

        $rows = $this->orderConsentsOf((int) $placed['orderId']);
        self::assertSame(
            [(string) $mandatory->getCode(), 'newsletter-api-test'],
            array_keys($rows),
            'Every consent the shop was asking for belongs on the order, refused ones included.',
        );

        $locale = (string) $order->getLang()->getLocale();
        self::assertTrue($rows[(string) $mandatory->getCode()]->isAccepted());
        self::assertSame(
            (string) $mandatory->setLocale($locale)->getTitle(),
            $rows[(string) $mandatory->getCode()]->getTitle(),
            'The order carries the wording the buyer was shown, not the code of the consent.',
        );
        self::assertNotNull($rows[(string) $mandatory->getCode()]->getAnsweredAt());
        self::assertSame('127.0.0.1', $rows[(string) $mandatory->getCode()]->getIpAddress());
        self::assertFalse(
            $rows['newsletter-api-test']->isAccepted(),
            'A consent the body refuses is recorded as refused, not left out.',
        );
    }

    /**
     * @return iterable<string, array{0: ?bool}>
     */
    public static function unacceptedMandatoryConsents(): iterable
    {
        yield 'no consents at all in the body' => [null];
        yield 'the consent explicitly refused' => [false];
    }

    /**
     * A mandatory consent left out of the body is exactly as unanswered as one refused
     * in it, and both name the consent the buyer still has to agree to.
     */
    #[DataProvider('unacceptedMandatoryConsents')]
    public function testAMandatoryConsentNotAcceptedInTheBodyRefusesThePlacementNamingIt(?bool $accepted): void
    {
        $checkout = $this->readyCheckout();
        $this->turnMandatoryConsentsBackOn();
        $mandatory = $this->aMandatoryConsent();
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();
        $this->walkTheSelections($cartId, $checkout, $token);

        $payload = null === $accepted
            ? []
            : ['consents' => [['code' => $mandatory->getCode(), 'accepted' => $accepted]]];

        $placement = $this->jsonRequest('POST', $this->url($cartId, 'place'), $payload, token: $token);

        self::assertSame(422, $placement->getStatusCode());
        $violations = $this->decode($placement)['violations'];
        self::assertContains(CheckoutViolationCode::ConsentMissing->value, array_column($violations, 'code'));

        $refusal = $this->theViolation($violations, CheckoutViolationCode::ConsentMissing->value);
        self::assertSame(
            (string) $mandatory->getCode(),
            $refusal['details']['consentCode'],
            'A client has to be told which box is still unticked.',
        );
        self::assertCount(0, OrderQuery::create()->filterByCartId($cartId)->find($this->getPropelConnection()));
    }

    /**
     * A body naming a consent the shop is not asking for is refused in the shape every
     * other refusal comes back in — never a 500, and never an order.
     */
    public function testAConsentCodeTheShopIsNotAskingForIsRefusedWithoutBreakingThePlacement(): void
    {
        $checkout = $this->readyCheckout();
        $this->turnMandatoryConsentsBackOn();
        $mandatory = $this->aMandatoryConsent();
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();
        $this->walkTheSelections($cartId, $checkout, $token);

        $placement = $this->jsonRequest('POST', $this->url($cartId, 'place'), [
            'consents' => [
                ['code' => $mandatory->getCode(), 'accepted' => true],
                ['code' => 'no-such-consent', 'accepted' => true],
            ],
        ], token: $token);

        self::assertSame(422, $placement->getStatusCode());
        $violations = $this->decode($placement)['violations'];
        self::assertContains(CheckoutViolationCode::ConsentUnknown->value, array_column($violations, 'code'));
        self::assertSame(
            'no-such-consent',
            $this->theViolation($violations, CheckoutViolationCode::ConsentUnknown->value)['details']['consentCode'],
        );
        self::assertCount(0, OrderQuery::create()->filterByCartId($cartId)->find($this->getPropelConnection()));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function bodiesThatDoNotSayWhatAConsentAnswerSays(): iterable
    {
        yield 'consents is not a list' => [['consents' => ['terms_and_conditions' => true]]];
        yield 'an answer with no code' => [['consents' => [['accepted' => true]]]];
        yield 'an answer with no verdict' => [['consents' => [['code' => 'terms_and_conditions']]]];
        yield 'a verdict that is not a boolean' => [['consents' => [['code' => 'terms_and_conditions', 'accepted' => 'yes']]]];
        yield 'the same consent answered twice' => [['consents' => [
            ['code' => 'terms_and_conditions', 'accepted' => true],
            ['code' => 'terms_and_conditions', 'accepted' => false],
        ]]];
    }

    /**
     * A body the shop cannot read as an answer is refused, and refused as a payload that
     * has to be corrected — never a 500, and never an order placed on a guess.
     *
     * @param array<string, mixed> $payload
     */
    #[DataProvider('bodiesThatDoNotSayWhatAConsentAnswerSays')]
    public function testABodyThatIsNotAConsentAnswerIsRefusedWithoutPlacingAnything(array $payload): void
    {
        $checkout = $this->readyCheckout();
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();
        $this->walkTheSelections($cartId, $checkout, $token);

        $placement = $this->jsonRequest('POST', $this->url($cartId, 'place'), $payload, token: $token);

        self::assertSame(422, $placement->getStatusCode());
        self::assertArrayNotHasKey(
            'violations',
            $this->decode($placement),
            'A body the shop cannot read is a payload to correct, not a cart with something left to settle.',
        );
        self::assertCount(0, OrderQuery::create()->filterByCartId($cartId)->find($this->getPropelConnection()));
    }

    /**
     * A list at the top level is the envelope forgotten, not an empty set of answers:
     * reading it as "nothing to record" would blame the buyer with a misleading
     * consent-missing for a body that carried every answer.
     */
    public function testABodyThatIsAListInsteadOfAnObjectIsRefusedAsABadRequest(): void
    {
        $checkout = $this->readyCheckout();
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();
        $this->walkTheSelections($cartId, $checkout, $token);

        $placement = $this->jsonRequest(
            'POST',
            $this->url($cartId, 'place'),
            [['code' => 'terms_and_conditions', 'accepted' => true]],
            token: $token,
        );

        self::assertSame(400, $placement->getStatusCode());
        self::assertCount(0, OrderQuery::create()->filterByCartId($cartId)->find($this->getPropelConnection()));
    }

    /**
     * The verdict takes no body, so it cannot know what the buyer is about to agree to:
     * it keeps reporting the consent as missing right up to the placement that carries
     * the answer. Pinned rather than left to surprise a client that polls it.
     */
    public function testTheVerdictStillReportsAMissingConsentBecauseItTakesNoBody(): void
    {
        $checkout = $this->readyCheckout();
        $this->turnMandatoryConsentsBackOn();
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();
        $this->walkTheSelections($cartId, $checkout, $token);

        $verdict = $this->decode($this->jsonRequest('GET', $this->url($cartId, 'validation'), token: $token));

        self::assertFalse($verdict['ready']);
        self::assertContains(
            CheckoutViolationCode::ConsentMissing->value,
            array_column($verdict['violations'], 'code'),
            'The consents are answered in the placement, so the verdict before it never sees them.',
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function everyOperation(array $checkout): iterable
    {
        yield 'delivery address' => ['POST', 'delivery_address', ['addressId' => $checkout['address']->getId()]];
        yield 'invoice address' => ['POST', 'invoice_address', ['addressId' => $checkout['address']->getId()]];
        yield 'delivery module' => ['POST', 'delivery_module', ['deliveryModuleId' => $checkout['deliveryModule']->getId()]];
        yield 'payment module' => ['POST', 'payment_module', ['paymentModuleId' => $checkout['paymentModule']->getId()]];
        yield 'validation' => ['GET', 'validation', []];
        yield 'placement' => ['POST', 'place', []];
    }

    private function walkTheSelections(int $cartId, array $checkout, string $token): void
    {
        $this->jsonRequest('POST', $this->url($cartId, 'delivery_address'), ['addressId' => $checkout['address']->getId()], token: $token);
        $this->jsonRequest('POST', $this->url($cartId, 'invoice_address'), ['addressId' => $checkout['address']->getId()], token: $token);
        $this->jsonRequest('POST', $this->url($cartId, 'delivery_module'), ['deliveryModuleId' => $checkout['deliveryModule']->getId()], token: $token);
        $this->jsonRequest('POST', $this->url($cartId, 'payment_module'), ['paymentModuleId' => $checkout['paymentModule']->getId()], token: $token);
    }

    private function url(int $cartId, string $step): string
    {
        return '/api/front/account/checkout/'.$cartId.'/'.$step;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\Symfony\Component\HttpFoundation\Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * The body of an error answer, with the fields that legitimately differ from one
     * request to the next taken out, so that two refusals can be compared word for word.
     */
    private function comparableBody(\Symfony\Component\HttpFoundation\Response $response): array
    {
        $body = json_decode((string) $response->getContent(), true) ?? [];

        unset($body['trace'], $body['hydra:trace'], $body['@id'], $body['hydra:description'], $body['detail']);

        // The description is the message, and comparing it is the whole point; it is put
        // back under one key so that a hydra body and a problem body compare the same.
        $message = json_decode((string) $response->getContent(), true) ?? [];
        $body['message'] = $message['hydra:description'] ?? $message['detail'] ?? $message['description'] ?? null;

        return $body;
    }

    /**
     * Takes off the shelf everything the cart asks for, without touching the cart: the
     * shop sold its last one between the moment the buyer filled the checkout in and the
     * moment they posted it.
     */
    private function emptyTheStockOf(Product $product): void
    {
        foreach (ProductSaleElementsQuery::create()->filterByProductId($product->getId())->find($this->getPropelConnection()) as $productSaleElements) {
            $productSaleElements->setQuantity(0)->save($this->getPropelConnection());
        }
    }

    /**
     * @return array{cart: Cart, customer: Customer, address: Address, country: Country, deliveryModule: Module, paymentModule: Module, product: Product}
     */
    private function readyCheckout(bool $virtual = false, bool $judgePaymentForReal = false): array
    {
        $factory = $this->createFixtureFactory();
        $country = $factory->country();

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No Cheque module installed — run bin/test-prepare.');

        $this->serveCountryWith($deliveryModule, $country);
        $this->answerDeliveryQuoteWith(valid: true);
        if (!$judgePaymentForReal) {
            $this->answerPaymentValidityWith(valid: true);
        }

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $address = $factory->address($customer, $country);

        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
            ['baseQuantity' => 100],
        );

        if ($virtual) {
            $product->setVirtual(1)->save($this->getPropelConnection());
        }

        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $product);

        return [
            'cart' => $cart,
            'customer' => $customer,
            'address' => $address,
            'country' => $country,
            'deliveryModule' => $deliveryModule,
            'paymentModule' => $paymentModule,
            'product' => $product,
        ];
    }

    private function anIdNoCartHas(): int
    {
        return 1 + (int) \Thelia\Model\CartQuery::create()->orderById(\Propel\Runtime\ActiveQuery\Criteria::DESC)->findOne($this->getPropelConnection())?->getId();
    }

    private function anIdNoAddressHas(): int
    {
        return 1 + (int) \Thelia\Model\AddressQuery::create()->orderById(\Propel\Runtime\ActiveQuery\Criteria::DESC)->findOne($this->getPropelConnection())?->getId();
    }

    private int $postageQuotes = 0;

    private function countPostageQuotes(): int
    {
        return $this->postageQuotes;
    }

    private function serveCountryWith(Module $module, Country $country): void
    {
        $area = (new Area())->setName('Checkout API test area '.uniqid());
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($module->getId())->save($this->getPropelConnection());
    }

    private function answerDeliveryQuoteWith(bool $valid): void
    {
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, function (DeliveryPostageEvent $event) use ($valid): void {
            ++$this->postageQuotes;
            $event->setValidModule($valid);
            if ($valid) {
                $event->setPostage(new OrderPostage(self::POSTAGE_AMOUNT, self::POSTAGE_TAX, 'VAT'));
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

    private function turnMandatoryConsentsOff(): void
    {
        // Propel pools its objects for the whole process, and the transaction of the
        // previous test took its writes back without telling the pool: the consent handed
        // over here would still read "inactive" in memory while the row says otherwise,
        // and setting it inactive again would be a no-op that writes nothing.
        ConsentTableMap::clearInstancePool();

        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }

    /**
     * The consent every shop is installed with, read back rather than named: the code of
     * the seeded row is the shop's business, and a test that hardcodes it stops testing
     * the shop the day the installer words it differently.
     */
    private function aMandatoryConsent(): Consent
    {
        return ConsentQuery::create()
            ->filterByMandatory(1)
            ->filterByActive(1)
            ->findOne($this->getPropelConnection())
            ?? throw new \RuntimeException('No mandatory consent installed — run bin/test-prepare.');
    }

    private function createConsent(string $code, string $title, bool $mandatory = false): Consent
    {
        $consent = (new Consent())
            ->setCode($code)
            ->setMandatory($mandatory ? 1 : 0)
            ->setActive(1)
            ->setLocale('en_US')
            ->setTitle($title);
        $consent->save($this->getPropelConnection());

        $this->getService(ConsentProvider::class)->forgetCache();

        return $consent;
    }

    /**
     * @return array<string, OrderConsent> by consent code, in the order they were written
     */
    private function orderConsentsOf(int $orderId): array
    {
        $rows = [];

        foreach (OrderConsentQuery::create()->filterByOrderId($orderId)->orderById()->find($this->getPropelConnection()) as $row) {
            $rows[(string) $row->getConsentCode()] = $row;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $violations
     *
     * @return array<string, mixed>
     */
    private function theViolation(array $violations, string $code): array
    {
        foreach ($violations as $violation) {
            if ($code === $violation['code']) {
                return $violation;
            }
        }

        throw new \RuntimeException(\sprintf('No violation "%s" in the answer.', $code));
    }

    /**
     * Puts back what setUp() took away, for the tests that are about a shop asking for
     * a consent. The rows go back to what the installer wrote; the transaction of the
     * test takes the change away either way.
     */
    private function turnMandatoryConsentsBackOn(): void
    {
        ConsentTableMap::clearInstancePool();

        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(1)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
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
