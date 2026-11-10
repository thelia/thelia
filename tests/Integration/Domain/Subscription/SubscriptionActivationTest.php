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

use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;
use Thelia\Domain\Subscription\EventListener\SubscriptionActivationListener;
use Thelia\Domain\Subscription\SubscriptionFactory;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\Subscription;
use Thelia\Model\SubscriptionOrderQuery;
use Thelia\Model\SubscriptionPlan;
use Thelia\Model\SubscriptionQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Delivery\FixedPostageDeliveryModule;
use Thelia\Tests\Support\Payment\RecurringTestPaymentModule;

/**
 * The subscription is taken when the order that carried it turns paid, once.
 */
final class SubscriptionActivationTest extends ActionIntegrationTestCase
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

    public function testAnOrderTurnedPaidTakesOneSubscriptionWithTheMethodOfTheCart(): void
    {
        [$product, $plan, $customer] = $this->aShopper();
        $method = $this->recurringMethodOf($customer, $this->recurringModule, $this->getPropelConnection());
        $cart = $this->aSubscriptionCart($customer, $product, $plan);
        $cart->setCustomerPaymentMethodId($method->getId())->save($this->getPropelConnection());
        $order = $this->anUnpaidOrderOf($cart, $customer);

        $this->turnPaid($order);

        $subscription = $this->onlySubscriptionOf($order);
        self::assertSame(SubscriptionStatus::Active->value, $subscription->getStatus());
        self::assertSame((int) $method->getId(), (int) $subscription->getCustomerPaymentMethodId());
        self::assertSame((int) $plan->getId(), (int) $subscription->getSubscriptionPlanId());
        self::assertSame(1, (int) $subscription->getInstallmentsBilled(), 'The order is the first installment.');
        self::assertSame(1, SubscriptionOrderQuery::create()->filterBySubscriptionId($subscription->getId())->count());
    }

    public function testASecondPaidEventForTheSameOrderLeavesOneSubscription(): void
    {
        [$product, $plan, $customer] = $this->aShopper();
        $cart = $this->aSubscriptionCart($customer, $product, $plan);
        $order = $this->anUnpaidOrderOf($cart, $customer);
        $this->turnPaid($order);

        // The event as a notification replayed by the provider raises it, from a status that is not paid.
        $notPaid = (int) OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_NOT_PAID)->getId();
        $listener = new SubscriptionActivationListener($this->getService(SubscriptionFactory::class));
        $event = (new OrderEvent(OrderQuery::create()->findPk($order->getId())))->setPreviousStatusId($notPaid);
        $listener->takeTheSubscriptionOfAPaidOrder($event);
        $listener->takeTheSubscriptionOfAPaidOrder($event);

        $this->onlySubscriptionOf($order);
    }

    public function testTheOrderOfAnInstallmentDoesNotTakeAnotherSubscription(): void
    {
        [$subscription] = $this->aSubscription();
        $this->moveTo('2028-03-01 10:00:00');
        $this->biller()->bill((int) $subscription->getId());

        $orders = $this->installmentOrdersOf($this->reloaded($subscription));
        self::assertCount(1, $orders);

        $this->turnPaid($orders[0]);

        self::assertSame(1, SubscriptionQuery::create()->filterByCustomerId($subscription->getCustomerId())->count());
    }

    public function testTheMethodTheModuleRegisteredWithTheOrderIsTheOneTaken(): void
    {
        [$product, $plan, $customer] = $this->aShopper();
        $cart = $this->aSubscriptionCart($customer, $product, $plan);
        $order = $this->anUnpaidOrderOf($cart, $customer);
        $old = $this->recurringMethodOf($customer, $this->recurringModule, $this->getPropelConnection());
        CustomerPaymentMethodQuery::create()->filterById($old->getId())->update(['CreatedAt' => new \DateTime('2000-01-01')]);
        $registeredWithTheOrder = $this->recurringMethodOf($customer, $this->recurringModule, $this->getPropelConnection());

        $this->turnPaid($order);

        self::assertSame((int) $registeredWithTheOrder->getId(), (int) $this->onlySubscriptionOf($order)->getCustomerPaymentMethodId());
    }

    public function testWithNoMethodTheSubscriptionIsTakenAllTheSame(): void
    {
        [$product, $plan, $customer] = $this->aShopper();
        $order = $this->anUnpaidOrderOf($this->aSubscriptionCart($customer, $product, $plan), $customer);

        $this->turnPaid($order);

        self::assertNull($this->onlySubscriptionOf($order)->getCustomerPaymentMethodId());
    }

    public function testAnOrderWithNoSubscriptionLineTakesNone(): void
    {
        $customer = $this->aCustomer();
        $cart = $this->factory->cart($customer);
        $this->factory->cartItem($cart, $this->aProduct());
        $order = $this->anUnpaidOrderOf($cart, $customer);

        $this->turnPaid($order);

        self::assertSame(0, SubscriptionQuery::create()->filterByCustomerId($customer->getId())->count());
    }

    /**
     * The whole way, with no money to take: the module registers the method at 0, turns the
     * order paid, and the subscription starts as a trial.
     */
    public function testAnOrderAtZeroPlacedWithTheRecurringModuleReachesPaidAndStartsATrial(): void
    {
        $product = $this->aProduct(10, true);
        $plan = $this->aPlan($product, ['TrialDays' => 7]);
        $customer = $this->aCustomer();
        $method = $this->recurringMethodOf($customer, $this->recurringModule, $this->getPropelConnection());
        $cart = $this->aSubscriptionCart($customer, $product, $plan);
        $country = $this->factory->country();
        $cart
            ->setAddressDeliveryId($this->factory->cartAddress(null, $country)->getId())
            ->setAddressInvoiceId($this->factory->cartAddress(null, $country)->getId())
            ->setDeliveryModuleId($this->carrier->getId())
            ->setPaymentModuleId($this->recurringModule->getId())
            ->setCustomerPaymentMethodId($method->getId())
            ->setPostage('0')
            ->setPostageTax('0')
            ->save($this->getPropelConnection());
        $cart = CartQuery::create()->findPk($cart->getId(), $this->getPropelConnection());

        $outcome = $this->getService(CheckoutPaymentService::class)->payAndReturnOutcome(new OrderPaymentRequest(
            $cart,
            (int) $cart->getAddressDeliveryId(),
            (int) $cart->getAddressInvoiceId(),
            (int) $this->carrier->getId(),
            (int) $this->recurringModule->getId(),
            $this->factory->currency(),
            $this->factory->lang(),
            customerPaymentMethodId: (int) $method->getId(),
        ));

        $order = OrderQuery::create()->findPk($outcome->placedOrder->getId());
        self::assertSame(0.0, round($order->getTotalAmount(), 4), 'A trial is placed at nothing.');
        self::assertTrue($order->isPaid(false), 'The 0 order the recurring module settles is paid.');

        $subscription = $this->onlySubscriptionOf($order);
        self::assertSame(SubscriptionStatus::Trial->value, $subscription->getStatus());
        self::assertSame((int) $method->getId(), (int) $subscription->getCustomerPaymentMethodId());
        self::assertSame(0, (int) $subscription->getInstallmentsBilled());
    }

    /**
     * @return array{Product, SubscriptionPlan, Customer}
     */
    private function aShopper(): array
    {
        $product = $this->aProduct();

        return [$product, $this->aPlan($product), $this->aCustomer()];
    }

    private function aSubscriptionCart(Customer $customer, Product $product, SubscriptionPlan $plan): Cart
    {
        $cart = $this->factory->cart($customer);
        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO(
            $cart,
            (int) $product->getId(),
            (int) $this->saleElementsOf($product)->getId(),
            1,
            subscriptionPlanId: (int) $plan->getId(),
        ));

        return $cart;
    }

    private function anUnpaidOrderOf(Cart $cart, Customer $customer): Order
    {
        $order = $this->factory->order($customer, [
            'paymentModuleCode' => RecurringTestPaymentModule::getModuleCode(),
            'deliveryModuleCode' => FixedPostageDeliveryModule::MODULE_CODE,
        ]);
        $order->setCartId($cart->getId())->save($this->getPropelConnection());

        return $order;
    }

    private function turnPaid(Order $order): void
    {
        $this->getService('event_dispatcher')->dispatch(
            (new OrderEvent($order))->setStatus((int) OrderStatusQuery::getPaidStatus()->getId()),
            TheliaEvents::ORDER_UPDATE_STATUS,
        );
    }

    private function onlySubscriptionOf(Order $order): Subscription
    {
        $subscriptions = SubscriptionQuery::create()->filterByInitialOrderId($order->getId())->find();
        self::assertCount(1, $subscriptions, 'One order, one subscription.');

        return $subscriptions->getFirst();
    }
}
