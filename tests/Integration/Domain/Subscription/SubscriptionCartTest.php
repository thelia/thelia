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

namespace Thelia\Tests\Integration\Domain\Subscription;

use FreeOrder\FreeOrder;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Currency\CurrencyChangeEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Exception\GuestCheckoutNotAllowedException;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Subscription\Exception\SubscriptionCartRefusedException;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Currency;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Product;
use Thelia\Model\SubscriptionPlan;
use Thelia\Model\SubscriptionPlanPrice;
use Thelia\Model\SubscriptionPlanPriceQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\PaymentModuleInterface;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Payment\RecurringTestPaymentModule;

/**
 * A subscription is bought on its own, by a signed-in customer, on a plan of the product, and
 * paid with a module that can charge its installments.
 */
final class SubscriptionCartTest extends ActionIntegrationTestCase
{
    use SubscriptionScenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTheSubscriptionShop();
    }

    protected function tearDown(): void
    {
        $this->tearDownTheSubscriptionShop();

        parent::tearDown();
    }

    public function testAPlanLineIsPricedByThePlanAndHoldsItsPlan(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($product, [], '9.900000');
        $cart = $this->factory->cart($this->aCustomer());

        $line = $this->addPlanLine($cart, $product, $plan, 2);

        self::assertSame((int) $plan->getId(), (int) $line->getSubscriptionPlanId());
        self::assertSame('9.900000', (string) $line->getPrice(), 'Not the 10.0 of the catalog.');
        self::assertSame(2.0, (float) $line->getQuantity());
    }

    public function testChangingTheCurrencyOfTheCartPricesAPlanLineAtThePriceOfThePlanInThatCurrency(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($product, [], '9.900000');
        $other = $this->factory->currency(['code' => 'XTS', 'rate' => 2.0 * (float) Currency::getDefaultCurrency()->getRate()]);
        (new SubscriptionPlanPrice())
            ->setSubscriptionPlanId($plan->getId())
            ->setCurrencyId($other->getId())
            ->setPrice('21.500000')
            ->setFromDefaultCurrency(0)
            ->save($this->getPropelConnection());
        $cart = $this->factory->cart($this->aCustomer());
        $line = $this->addPlanLine($cart, $product, $plan);
        self::assertSame('9.900000', (string) $line->getPrice());

        $this->changeTheCurrencyOfTheVisitor($cart, $other);

        $line->reload();
        self::assertSame('21.500000', (string) $line->getPrice(), 'The price typed for the plan in that currency, not the 10.0 of the catalog nor the conversion.');
        $cart->reload();
        self::assertSame((int) $other->getId(), (int) $cart->getCurrencyId());
    }

    public function testAPlanWithNoPriceInTheNewCurrencyIsConvertedFromItsPriceInTheDefaultCurrency(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($product, [], '9.900000');
        $other = $this->factory->currency(['code' => 'XTS', 'rate' => 2.0 * (float) Currency::getDefaultCurrency()->getRate()]);
        $cart = $this->factory->cart($this->aCustomer());
        $line = $this->addPlanLine($cart, $product, $plan);

        $this->changeTheCurrencyOfTheVisitor($cart, $other);

        $line->reload();
        self::assertSame('19.800000', (string) $line->getPrice(), 'The rule of the plan: 9.9 in the default currency, at the rate of the currency.');
    }

    public function testAPlanNothingPricesInTheNewCurrencyKeepsThePriceOfItsLine(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($product, [], '9.900000');
        $other = $this->factory->currency(['code' => 'XTS', 'rate' => 2.0 * (float) Currency::getDefaultCurrency()->getRate()]);
        $cart = $this->factory->cart($this->aCustomer());
        $line = $this->addPlanLine($cart, $product, $plan);
        SubscriptionPlanPriceQuery::create()->filterBySubscriptionPlanId($plan->getId())->delete($this->getPropelConnection());

        $this->changeTheCurrencyOfTheVisitor($cart, $other);

        $line->reload();
        self::assertSame('9.900000', (string) $line->getPrice(), 'As a sale element nothing prices: the line keeps the price it holds, and nothing is raised.');
    }

    public function testAPlanWithATrialIsPricedAtNothing(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($product, ['TrialDays' => 7], '9.900000');
        $cart = $this->factory->cart($this->aCustomer());

        $line = $this->addPlanLine($cart, $product, $plan);

        self::assertSame(0.0, (float) $line->getPrice());
    }

    public function testAnotherLineIsRefusedInACartThatHoldsASubscription(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($product);
        $cart = $this->factory->cart($this->aCustomer());
        $this->addPlanLine($cart, $product, $plan);

        $this->expectException(SubscriptionCartRefusedException::class);

        try {
            $other = $this->aProduct();
            $this->getService(CartFacade::class)->addItem(new CartItemAddDTO($cart, (int) $other->getId(), (int) $this->saleElementsOf($other)->getId()));
        } finally {
            self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->count(), 'The cart is left as it was.');
        }
    }

    public function testASubscriptionIsRefusedInACartThatHoldsAnotherLine(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($product);
        $cart = $this->factory->cart($this->aCustomer());
        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO($cart, (int) $product->getId(), (int) $this->saleElementsOf($product)->getId()));

        $this->expectException(SubscriptionCartRefusedException::class);

        $this->addPlanLine($cart, $product, $plan);
    }

    public function testASecondSubscriptionIsRefused(): void
    {
        $product = $this->aProduct();
        $cart = $this->factory->cart($this->aCustomer());
        $this->addPlanLine($cart, $product, $this->aPlan($product));

        $this->expectException(SubscriptionCartRefusedException::class);

        $this->addPlanLine($cart, $product, $this->aPlan($product));
    }

    public function testAPlanOfAnotherProductIsRefused(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($this->aProduct());
        $cart = $this->factory->cart($this->aCustomer());

        $this->expectException(SubscriptionCartRefusedException::class);

        $this->addPlanLine($cart, $product, $plan);
    }

    public function testAPlanThatIsNotVisibleIsRefused(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($product, ['Visible' => 0]);
        $cart = $this->factory->cart($this->aCustomer());

        $this->expectException(SubscriptionCartRefusedException::class);

        $this->addPlanLine($cart, $product, $plan);
    }

    public function testAVisitorWhoIsNotSignedInCannotSubscribe(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($product);

        $this->expectException(SubscriptionCartRefusedException::class);

        $this->addPlanLine($this->factory->cart(null), $product, $plan);
    }

    public function testOnlyTheModulesThatCanChargeAreOfferedForASubscriptionCart(): void
    {
        $product = $this->aProduct();
        $cart = $this->factory->cart($this->aCustomer());
        $this->addPlanLine($cart, $product, $this->aPlan($product));
        $cart = $this->rereadCart($cart);

        self::assertTrue($this->isValid(new RecurringTestPaymentModule(), $cart), 'The module that charges installments is offered.');

        RecurringTestPaymentModule::$chargesOffSession = false;
        self::assertFalse($this->isValid(new RecurringTestPaymentModule(), $cart), 'Not once it says it cannot charge right now.');
        RecurringTestPaymentModule::$chargesOffSession = true;

        $freeOrder = $this->otherPaymentModule();
        self::assertFalse($this->isValid($freeOrder, $cart), 'A module that cannot charge installments (FreeOrder included) is not offered.');
    }

    public function testACartWithNoSubscriptionIsJudgedAsBefore(): void
    {
        $cart = $this->factory->cart($this->aCustomer());
        $this->factory->cartItem($cart, $this->aProduct());

        self::assertTrue($this->isValid(new RecurringTestPaymentModule(), $this->rereadCart($cart)));
    }

    public function testTheOrderIsNotPlacedWithAModuleThatCannotCharge(): void
    {
        $product = $this->aProduct();
        $cart = $this->factory->cart($this->aCustomer());
        $this->addPlanLine($cart, $product, $this->aPlan($product));
        $cart = $this->rereadCart($cart);
        $other = ModuleQuery::create()->filterByType(BaseModule::PAYMENT_MODULE_TYPE)->filterByActivate(1)->filterById($this->recurringModule->getId(), Criteria::NOT_EQUAL)->findOne();
        self::assertNotNull($other, 'A payment module other than the recurring one is installed.');

        $this->expectException(InvalidPaymentException::class);

        $this->place($cart, (int) $other->getId());
    }

    public function testTheOrderOfASubscriptionIsNotPlacedForAnAnonymousCart(): void
    {
        $product = $this->aProduct();
        $cart = $this->factory->cart(null);
        $line = $this->factory->cartItem($cart, $product);
        $line->setSubscriptionPlanId($this->aPlan($product)->getId())->save($this->getPropelConnection());

        $this->expectException(GuestCheckoutNotAllowedException::class);

        $this->place($this->rereadCart($cart), (int) $this->recurringModule->getId());
    }

    private function addPlanLine(Cart $cart, Product $product, SubscriptionPlan $plan, int $quantity = 1): CartItem
    {
        return $this->getService(CartFacade::class)->addItem(new CartItemAddDTO(
            $cart,
            (int) $product->getId(),
            (int) $this->saleElementsOf($product)->getId(),
            $quantity,
            subscriptionPlanId: (int) $plan->getId(),
        ));
    }

    /**
     * The visitor picks another currency: what the request listener dispatches, on the cart of the session.
     */
    private function changeTheCurrencyOfTheVisitor(Cart $cart, Currency $currency): void
    {
        $request = $this->getService(RequestStack::class)->getCurrentRequest();
        self::assertNotNull($request);
        $session = $request->getSession();
        self::assertInstanceOf(Session::class, $session);
        $session->setCustomerUser($cart->getCustomer());
        $session->setSessionCart($cart);

        try {
            $this->getService('event_dispatcher')->dispatch(new CurrencyChangeEvent($currency, $request), TheliaEvents::CHANGE_DEFAULT_CURRENCY);
        } finally {
            $session->setSessionCart(null);
            $session->setCustomerUser(null);
        }
    }

    private function isValid(PaymentModuleInterface $module, Cart $cart): bool
    {
        $module->setContainer(static::getContainer());
        $event = new IsValidPaymentEvent($module, $cart);
        $this->getService('event_dispatcher')->dispatch($event, TheliaEvents::MODULE_PAYMENT_IS_VALID);

        return $event->isValidModule();
    }

    private function otherPaymentModule(): PaymentModuleInterface
    {
        if (!class_exists(FreeOrder::class)) {
            self::markTestSkipped('FreeOrder is not installed.');
        }

        return new FreeOrder();
    }

    private function place(Cart $cart, int $paymentModuleId): void
    {
        $this->getService(CheckoutPaymentService::class)->payAndReturnOutcome(new OrderPaymentRequest(
            $cart,
            (int) $cart->getAddressDeliveryId(),
            (int) $cart->getAddressInvoiceId(),
            (int) $this->carrier->getId(),
            $paymentModuleId,
        ));
    }

    private function rereadCart(Cart $cart): Cart
    {
        CartTableMap::removeInstanceFromPool($cart);

        return CartQuery::create()->findPk($cart->getId(), $this->getPropelConnection()) ?? throw new \RuntimeException('Cart gone.');
    }
}
