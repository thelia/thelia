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

namespace Thelia\Tests\Integration\Domain\Cart;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Checkout\Exception\InvalidDeliveryException;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The cart names the delivery and payment modules the customer picked. Before an
 * order is placed, the guard has to judge them the way the checkout offer did:
 * an installed row is not enough.
 */
final class CartGuardTest extends IntegrationTestCase
{
    private FixtureFactory $factory;
    private CartGuard $guard;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->guard = $this->getService(CartGuard::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        parent::tearDown();
    }

    public function testAnInactiveDeliveryModuleIsRefused(): void
    {
        $module = $this->deliveryModule();
        $module->setActivate(0)->save();
        $cart = $this->cartShippedWith($module, $this->factory->country());

        $this->expectException(InvalidDeliveryException::class);
        $this->guard->checkValidDelivery($cart);
    }

    public function testADeliveryModuleThatDoesNotServeTheCountryIsRefused(): void
    {
        $module = $this->deliveryModule();
        $cart = $this->cartShippedWith($module, $this->factory->country(['isoalpha2' => 'ZZ', 'isoalpha3' => 'ZZZ', 'isocode' => '999']));

        $this->expectException(InvalidDeliveryException::class);
        $this->guard->checkValidDelivery($cart);
    }

    public function testADeliveryModuleThatDeclinesTheCartIsRefused(): void
    {
        $module = $this->deliveryModule();
        $country = $this->factory->country();
        $this->serveCountryWith($module, $country);
        $this->answerDeliveryQuoteWith(valid: false);
        $cart = $this->cartShippedWith($module, $country);

        $this->expectException(InvalidDeliveryException::class);
        $this->guard->checkValidDelivery($cart);
    }

    public function testADeliveryModuleThatServesAndQuotesTheCartIsAccepted(): void
    {
        $module = $this->deliveryModule();
        $country = $this->factory->country();
        $this->serveCountryWith($module, $country);
        $this->answerDeliveryQuoteWith(valid: true);
        $cart = $this->cartShippedWith($module, $country);

        $this->guard->checkValidDelivery($cart);

        $this->addToAssertionCount(1);
    }

    public function testAnInactivePaymentModuleIsRefused(): void
    {
        $module = $this->paymentModule();
        $module->setActivate(0)->save();
        $cart = $this->factory->cart();
        $cart->setPaymentModuleId($module->getId())->save();

        $this->expectException(InvalidPaymentException::class);
        $this->guard->checkValidPayment($cart);
    }

    public function testAPaymentModuleThatDeclinesTheCartIsRefused(): void
    {
        $module = $this->paymentModule();
        $this->answerPaymentValidityWith(false);
        $cart = $this->factory->cart();
        $cart->setPaymentModuleId($module->getId())->save();

        $this->expectException(InvalidPaymentException::class);
        $this->guard->checkValidPayment($cart);
    }

    public function testAPaymentModuleThatAcceptsTheCartIsAccepted(): void
    {
        $module = $this->paymentModule();
        $this->answerPaymentValidityWith(true);
        $cart = $this->factory->cart();
        $cart->setPaymentModuleId($module->getId())->save();

        $this->guard->checkValidPayment($cart);

        $this->addToAssertionCount(1);
    }

    private function deliveryModule(): Module
    {
        return ModuleQuery::create()->filterByType(2)->filterByActivate(1)->findOne()
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');
    }

    private function paymentModule(): Module
    {
        return ModuleQuery::create()->filterByType(3)->filterByActivate(1)->findOne()
            ?? throw new \RuntimeException('No payment module installed — run bin/test-prepare.');
    }

    private function cartShippedWith(Module $module, Country $country): Cart
    {
        $address = $this->factory->cartAddress(null, $country);
        $cart = $this->factory->cart();
        $cart->setAddressDeliveryId($address->getId())->setDeliveryModuleId($module->getId())->save();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $this->factory->cartItem($cart, $product);

        return $cart;
    }

    private function serveCountryWith(Module $module, Country $country): void
    {
        $area = (new Area())->setName('Guard test area');
        $area->save();
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save();
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($module->getId())->save();
    }

    private function answerDeliveryQuoteWith(bool $valid): void
    {
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event) use ($valid): void {
            $event->setValidModule($valid);
            if ($valid) {
                $event->setPostage(new OrderPostage(5.0, 1.0, 'VAT'));
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

    private function listen(string $eventName, callable $listener): void
    {
        $this->dispatcher()->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }
}
