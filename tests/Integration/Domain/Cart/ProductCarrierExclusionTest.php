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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Catalog\Product\Exception\NotAnActiveDeliveryModuleException;
use Thelia\Domain\Catalog\Product\ProductFacade;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Exception\DeliveryExcludedByProductException;
use Thelia\Domain\Shipping\Service\DeliveryModuleEligibilityChecker;
use Thelia\Domain\Shipping\Service\PostageEstimator;
use Thelia\Domain\Shipping\Service\PostageHandler;
use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\Product;
use Thelia\Model\ProductExcludedDeliveryModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * A carrier a product of the cart cannot travel with is not offered, and an order
 * holding that product is refused on it, with the product named.
 */
final class ProductCarrierExclusionTest extends IntegrationTestCase
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

        parent::tearDown();
    }

    public function testACarrierAProductRefusesIsNotOfferedForTheCart(): void
    {
        $carrier = $this->carrier();
        $country = $this->factory->country();
        $this->serveCountryWith($carrier, $country);
        [$cart, $product] = $this->cartShippedWith($carrier, $country);
        $checker = $this->getService(DeliveryModuleEligibilityChecker::class);

        self::assertTrue($checker->isEligible($carrier, $cart, $country, null));

        $this->getService(ProductFacade::class)->excludeDeliveryModule($product->getId(), $carrier->getId());

        self::assertFalse($checker->isEligible($carrier, $cart, $country, null));
    }

    public function testAnOrderOnACarrierAProductRefusesIsRefusedWithTheProductNamed(): void
    {
        $carrier = $this->carrier();
        $country = $this->factory->country();
        $this->serveCountryWith($carrier, $country);
        [$cart, $product] = $this->cartShippedWith($carrier, $country, 'Garden furnace');
        $this->answerDeliveryQuote();
        $this->getService(ProductFacade::class)->excludeDeliveryModule($product->getId(), $carrier->getId());

        try {
            $this->getService(CartGuard::class)->checkValidDelivery($cart);
            self::fail('A carrier the product refuses must not carry the order.');
        } catch (DeliveryExcludedByProductException $refusal) {
            self::assertSame(CheckoutViolationCode::DeliveryExcludedByProduct->value, $refusal->violationCode());
            self::assertSame([$product->getId()], $refusal->violationDetails()['productIds']);
            self::assertStringContainsString('Garden furnace', $refusal->getMessage());
        }
    }

    public function testTheProductsThatLeaveTheCartWithNoCarrierAreNamed(): void
    {
        $country = $this->factory->country();
        $carriers = ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->find();
        self::assertGreaterThan(0, $carriers->count());

        foreach ($carriers as $carrier) {
            $this->serveCountryWith($carrier, $country);
        }

        [$cart, $product] = $this->cartShippedWith($carriers->getFirst(), $country);
        $checker = $this->getService(DeliveryModuleEligibilityChecker::class);

        self::assertSame([], $checker->productsBlockingEveryCarrier($cart, $country, null), 'Every carrier is still open.');

        foreach ($carriers as $carrier) {
            $this->getService(ProductFacade::class)->excludeDeliveryModule($product->getId(), $carrier->getId());
        }

        self::assertSame([$product->getId()], $checker->productsBlockingEveryCarrier($cart, $country, null));
    }

    public function testTheRefusalAgreesWithTheNumberOfProductsItNames(): void
    {
        $one = new DeliveryExcludedByProductException([7 => 'Garden furnace'], true);
        $two = new DeliveryExcludedByProductException([7 => 'Garden furnace', 8 => 'Gas cylinder'], true);

        self::assertStringContainsString('Garden furnace. Remove it from your cart', $one->getMessage());
        self::assertStringContainsString('Garden furnace, Gas cylinder. Remove them from your cart', $two->getMessage());
    }

    public function testOnlyAnActiveDeliveryModuleCanBeExcluded(): void
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $paymentModule = ModuleQuery::create()->filterByType(BaseModule::PAYMENT_MODULE_TYPE)->findOne();
        self::assertNotNull($paymentModule);

        try {
            $this->getService(ProductFacade::class)->excludeDeliveryModule($product->getId(), $paymentModule->getId());
            self::fail('A payment module is not a carrier a product can refuse.');
        } catch (NotAnActiveDeliveryModuleException) {
            self::assertFalse(ProductExcludedDeliveryModuleQuery::create()->filterByProductId($product->getId())->exists());
        }
    }

    public function testAllowingACarrierAgainOffersItOnceMore(): void
    {
        $carrier = $this->carrier();
        $country = $this->factory->country();
        $this->serveCountryWith($carrier, $country);
        [$cart, $product] = $this->cartShippedWith($carrier, $country);
        $facade = $this->getService(ProductFacade::class);
        $facade->excludeDeliveryModule($product->getId(), $carrier->getId());
        // Twice: the second call changes nothing.
        $facade->excludeDeliveryModule($product->getId(), $carrier->getId());

        $facade->allowDeliveryModule($product->getId(), $carrier->getId());

        self::assertTrue($this->getService(DeliveryModuleEligibilityChecker::class)->isEligible($carrier, $cart, $country, null));
    }

    public function testTheEstimateMovesToAnotherCarrierWhenAProductRefusesTheOneOfTheSession(): void
    {
        [$sessionCarrier, $otherCarrier] = $this->twoCarriers();
        $country = $this->factory->country();
        $this->serveCountryWith($sessionCarrier, $country);
        $this->serveCountryWith($otherCarrier, $country);
        [$cart, $product] = $this->cartShippedWith($sessionCarrier, $country);
        $this->answerDeliveryQuoteByCarrier([$sessionCarrier->getCode() => 9.0, $otherCarrier->getCode() => 4.0]);
        $this->session()->getOrder()->setDeliveryModuleId($sessionCarrier->getId());
        $this->getService(ProductFacade::class)->excludeDeliveryModule($product->getId(), $sessionCarrier->getId());

        $estimate = $this->getService(PostageEstimator::class)->estimatePostageForCountry($cart, $country);

        self::assertNotNull($estimate->getBestPostageAmount(), 'A carrier is left: the estimate is not empty.');
        self::assertEqualsWithDelta(4.0, $estimate->getBestPostageAmount(), 0.0001);
    }

    public function testTheEstimateKeepsToTheCarrierOfTheSessionWhileNoProductRefusesIt(): void
    {
        [$sessionCarrier, $otherCarrier] = $this->twoCarriers();
        $country = $this->factory->country();
        $this->serveCountryWith($sessionCarrier, $country);
        $this->serveCountryWith($otherCarrier, $country);
        [$cart] = $this->cartShippedWith($sessionCarrier, $country);
        $this->answerDeliveryQuoteByCarrier([$sessionCarrier->getCode() => 9.0, $otherCarrier->getCode() => 4.0]);
        $this->session()->getOrder()->setDeliveryModuleId($sessionCarrier->getId());

        $estimate = $this->getService(PostageEstimator::class)->estimatePostageForCountry($cart, $country);

        self::assertEqualsWithDelta(9.0, $estimate->getBestPostageAmount(), 0.0001, 'The carrier the buyer picked is the one estimated, cheaper or not.');
    }

    public function testAProductThatRefusesTheChosenCarrierTakesItOffTheCartWithItsPostage(): void
    {
        $carrier = $this->carrier();
        $country = $this->factory->country();
        $this->serveCountryWith($carrier, $country);
        [$cart] = $this->cartShippedWith($carrier, $country);
        $this->answerDeliveryQuote();
        $postageHandler = $this->getService(PostageHandler::class);
        $postageHandler->handlePostageOnCart($cart);
        self::assertSame($carrier->getId(), CartQuery::create()->findPk($cart->getId())?->getDeliveryModuleId(), 'The carrier quotes while no product refuses it.');

        // A product refusing the carrier joins the cart, which then asks for its postage again.
        $refusingProduct = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $this->getService(ProductFacade::class)->excludeDeliveryModule($refusingProduct->getId(), $carrier->getId());
        $this->factory->cartItem($cart, $refusingProduct);
        $cart->clearCartItems();
        $postageHandler->handlePostageOnCart($cart);

        $cart = CartQuery::create()->findPk($cart->getId());
        self::assertNotNull($cart);
        self::assertNull($cart->getDeliveryModuleId());
        self::assertNull($cart->getPostage());
        self::assertEqualsWithDelta(0.0, (float) $cart->getPostageTax(), 0.0001);
    }

    public function testACarrierAProductOfTheCartRefusesCannotBeChosen(): void
    {
        $carrier = $this->carrier();
        $country = $this->factory->country();
        $this->serveCountryWith($carrier, $country);
        [$cart, $product] = $this->cartShippedWith($carrier, $country, 'Garden furnace');
        $cart->setDeliveryModuleId(null)->save();
        $this->getService(ProductFacade::class)->excludeDeliveryModule($product->getId(), $carrier->getId());

        try {
            $this->getService(ShippingFacade::class)->chooseDeliveryModule($cart, $carrier->getId());
            self::fail('A carrier the product refuses must not be chosen.');
        } catch (DeliveryExcludedByProductException $refusal) {
            self::assertSame([$product->getId()], $refusal->violationDetails()['productIds']);
            self::assertStringContainsString('Garden furnace', $refusal->getMessage());
        }

        self::assertNull(CartQuery::create()->findPk($cart->getId())?->getDeliveryModuleId());
    }

    private function carrier(): Module
    {
        return ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->findOne()
            ?? throw new \RuntimeException('No delivery module installed - run bin/test-prepare.');
    }

    /**
     * @return array{0: Cart, 1: Product}
     */
    private function cartShippedWith(Module $carrier, Country $country, ?string $title = null): array
    {
        $address = $this->factory->cartAddress(null, $country);
        $cart = $this->factory->cart();
        $cart->setAddressDeliveryId($address->getId())->setDeliveryModuleId($carrier->getId())->save();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            null !== $title ? ['title' => $title] : [],
        );
        $this->factory->cartItem($cart, $product);

        return [$cart, $product];
    }

    private function serveCountryWith(Module $carrier, Country $country): void
    {
        $area = (new Area())->setName('Exclusion test area');
        $area->save();
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save();
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($carrier->getId())->save();
    }

    private function answerDeliveryQuote(): void
    {
        $listener = static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(5.0, 1.0, 'VAT'));
            $event->stopPropagation();
        };
        $this->dispatcher()->addListener(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, $listener, 512);
        $this->registeredListeners[] = [TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, $listener];
    }

    /**
     * @return array{0: Module, 1: Module}
     */
    private function twoCarriers(): array
    {
        $carriers = ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->orderById()->limit(2)->find();

        if (2 !== $carriers->count()) {
            self::markTestSkipped('Two active delivery modules are needed.');
        }

        return [$carriers[0], $carriers[1]];
    }

    /**
     * Each carrier quotes its own price, tax included, with a fifth of it as tax.
     *
     * @param array<string, float> $prices module code => price
     */
    private function answerDeliveryQuoteByCarrier(array $prices): void
    {
        $listener = static function (DeliveryPostageEvent $event) use ($prices): void {
            $price = $prices[$event->getModule()->getCode()] ?? null;

            if (null === $price) {
                return;
            }

            $event->setValidModule(true);
            $event->setPostage(new OrderPostage($price, round($price / 6, 2), 'VAT'));
            $event->stopPropagation();
        };
        $this->dispatcher()->addListener(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, $listener, 512);
        $this->registeredListeners[] = [TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, $listener];
    }

    /**
     * The session the estimator is given, which holds the carrier the buyer picked.
     */
    private function session(): Session
    {
        return $this->getService(Session::class);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
