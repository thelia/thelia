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
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Exception\CheckoutException;
use Thelia\Domain\Checkout\Exception\EmptyCartException;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Domain\Checkout\Exception\MissingAddressException;
use Thelia\Domain\Checkout\Service\CheckoutValidationService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Checkout\Service\Step\CartStepProvider;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepProviderInterface;
use Thelia\Domain\Checkout\Service\Step\ConfirmationStepProvider;
use Thelia\Domain\Checkout\Service\Step\DeliveryStepProvider;
use Thelia\Domain\Checkout\Service\Step\PaymentStepProvider;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CheckoutStep;
use Thelia\Model\CheckoutStepQuery;
use Thelia\Model\Consent;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Integration\Domain\Checkout\Fixture\RefusedByFixtureException;
use Thelia\Tests\Integration\Domain\Checkout\Fixture\RefusingStepProvider;

/**
 * What is checked when the order is actually placed.
 *
 * The promise the whole feature rests on: removing a step removes its screen, never its
 * check. So the placement runs the check of every step the installed code declares —
 * the four of the core and the ones modules ship — whatever the `checkout_step` table
 * says about them. A step with no row, a step turned off and a step this cart skips are
 * all still checked here, because none of those three things is a reason to let an
 * order through that the step would have refused.
 */
final class CheckoutPlacementGuardsTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
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
     * The reference behaviour every other case here is measured against: a cart with an
     * address, a carrier, a payment and nothing left to tick is placed.
     */
    public function testACartWithEverythingSettledIsPlaced(): void
    {
        $this->expectNotToPerformAssertions();
        $this->turnMandatoryConsentsOff();
        $cart = $this->cartReadyToPay();

        $this->validationWith($this->coreProviders())->validateForOrder($cart);
    }

    /**
     * A module shipping a step the shop never synchronised has no row in the table, and
     * that is the most likely state of a module just installed. Its check still refuses.
     */
    public function testAStepWithNoRowInTheTableStillRefusesTheOrder(): void
    {
        $this->turnMandatoryConsentsOff();
        $cart = $this->cartReadyToPay();

        self::assertNull(
            $this->rowFor(RefusingStepProvider::CODE),
            'The fixture step must have no row for this test to mean anything.',
        );

        $this->expectException(RefusedByFixtureException::class);

        $this->validationWith([...$this->coreProviders(), new RefusingStepProvider()])->validateForOrder($cart);
    }

    /**
     * The one the whole feature is about: the merchant turned the step off, so the buyer
     * no longer walks through its screen — and the order is still refused while what it
     * asked for is not settled.
     */
    public function testAStepTurnedOffInTheTableStillRefusesTheOrder(): void
    {
        $this->turnMandatoryConsentsOff();
        $this->seedRowFor(RefusingStepProvider::CODE, active: false);
        $cart = $this->cartReadyToPay();

        $this->expectException(RefusedByFixtureException::class);

        $this->validationWith([...$this->coreProviders(), new RefusingStepProvider()])->validateForOrder($cart);
    }

    /**
     * A step that answers "this cart has nothing to do here" is left out of the tunnel
     * for the display, and checked all the same: the delivery of a virtual cart is
     * exactly that, and an order with no carrier on it cannot be shipped.
     */
    public function testAStepThisCartSkipsStillRefusesTheOrder(): void
    {
        $this->turnMandatoryConsentsOff();
        $this->seedRowFor(RefusingStepProvider::CODE, active: true);
        $cart = $this->cartReadyToPay();

        $this->expectException(RefusedByFixtureException::class);

        $this->validationWith([...$this->coreProviders(), new RefusingStepProvider(skipped: true)])->validateForOrder($cart);
    }

    /**
     * The buyer is told about the first thing they have to go back and do, not about the
     * last: the order of the refusals is the order of the tunnel. This pins the exact
     * sequence the checkout refused in before the steps became configurable — cart,
     * delivery, then the payment screen (legal identifiers, payment module, consents).
     */
    public function testTheOrderIsRefusedInTheOrderOfTheTunnel(): void
    {
        $this->turnMandatoryConsentsOff();
        $validation = $this->validationWith($this->coreProviders());

        $empty = $this->factory->cart();

        try {
            $validation->validateForOrder($empty);
            self::fail('An empty cart cannot be ordered.');
        } catch (CheckoutException $exception) {
            self::assertInstanceOf(EmptyCartException::class, $exception);
        }

        $noDelivery = $this->cartWithAnItem();

        try {
            $validation->validateForOrder($noDelivery);
            self::fail('A cart with no carrier cannot be ordered.');
        } catch (CheckoutException $exception) {
            self::assertInstanceOf(
                MissingAddressException::class,
                $exception,
                'The delivery is asked about before the payment screen is, and it starts with the address.',
            );
        }

        $noPayment = $this->cartReadyToPay();
        $noPayment->setPaymentModuleId(null)->save($this->getPropelConnection());

        try {
            $validation->validateForOrder($noPayment);
            self::fail('A cart with no payment module cannot be ordered.');
        } catch (CheckoutException $exception) {
            self::assertInstanceOf(InvalidPaymentException::class, $exception);
        }
    }

    /**
     * @param list<CheckoutStepProviderInterface> $providers
     */
    private function validationWith(array $providers): CheckoutValidationService
    {
        return new CheckoutValidationService($providers);
    }

    /**
     * Deliberately not in tunnel order: the service is what puts them back in it.
     *
     * @return list<CheckoutStepProviderInterface>
     */
    private function coreProviders(): array
    {
        return [
            $this->getService(ConfirmationStepProvider::class),
            $this->getService(PaymentStepProvider::class),
            $this->getService(CartStepProvider::class),
            $this->getService(DeliveryStepProvider::class),
        ];
    }

    private function rowFor(string $code): ?CheckoutStep
    {
        return CheckoutStepQuery::create()->findOneByCode($code, $this->getPropelConnection());
    }

    private function seedRowFor(string $code, bool $active): void
    {
        (new CheckoutStep())
            ->setCode($code)
            ->setPosition(2)
            ->setActive($active ? 1 : 0)
            ->setMandatory(0)
            ->save($this->getPropelConnection());
    }

    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            /* @var Consent $consent */
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }

    private function cartWithAnItem(): Cart
    {
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        ));

        return $cart;
    }

    private function cartReadyToPay(): Cart
    {
        $country = $this->factory->country();

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No payment module installed — run bin/test-prepare.');

        $this->serveCountryWith($deliveryModule, $country);
        $this->answerDeliveryQuoteWith(valid: true);
        $this->answerPaymentValidityWith(valid: true);

        $cart = $this->cartWithAnItem();
        $cart
            ->setAddressDeliveryId($this->factory->cartAddress(null, $country)->getId())
            ->setAddressInvoiceId($this->factory->cartAddress(null, $country)->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId())
            ->save($this->getPropelConnection());

        return $cart;
    }

    private function serveCountryWith(Module $module, Country $country): void
    {
        $area = (new Area())->setName('Placement guards test area');
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($module->getId())->save($this->getPropelConnection());
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
