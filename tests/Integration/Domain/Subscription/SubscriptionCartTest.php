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
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
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
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Product;
use Thelia\Model\SubscriptionPlan;
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
