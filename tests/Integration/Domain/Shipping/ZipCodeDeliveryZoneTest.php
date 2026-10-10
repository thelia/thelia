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

namespace Thelia\Tests\Integration\Domain\Shipping;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Checkout\Exception\InvalidDeliveryException;
use Thelia\Domain\Shipping\Service\DeliveryModuleEligibilityChecker;
use Thelia\Domain\Shipping\Service\PostageEstimator;
use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaZipCodeRule;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * A delivery zone narrowed down by postal codes: the carriers it brings are offered, quoted
 * and accepted only for the addresses whose postal code the zone accepts.
 */
final class ZipCodeDeliveryZoneTest extends IntegrationTestCase
{
    private FixtureFactory $factory;
    private Module $module;
    private Country $country;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->module = ModuleQuery::create()->filterByType(2)->filterByActivate(1)->orderByPosition()->findOne()
            ?? throw new \RuntimeException('No delivery module installed: run bin/test-prepare.');
        // A country no seeded zone holds, so that only the zones of the test decide.
        $this->country = $this->newCountry('XQ', 'XQX', '990');
        $this->quoteEveryCart();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        parent::tearDown();
    }

    public function testAnExcludedPrefixHidesTheCarrierFromTheAddressesItMatchesOnly(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);

        self::assertFalse($this->isEligible('20090'), 'An address in Corsica does not see the carrier.');
        self::assertTrue($this->isEligible('69001'), 'An address in Lyon does.');
    }

    public function testARangeAcceptsTheAddressesInsideItOnly(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '75001...75020');

        self::assertTrue($this->isEligible('75 011'));
        self::assertFalse($this->isEligible('75116'));
    }

    public function testAnotherZoneOfTheCarrierCanAcceptWhatTheFirstOneRefuses(): void
    {
        $mainland = $this->zoneServedByTheModule();
        $this->rule($mainland, '20*', excluded: true);
        $corsica = $this->zoneServedByTheModule();
        $this->rule($corsica, '20*');

        self::assertTrue($this->isEligible('20090'));
    }

    public function testARuleWrittenForAnotherCountryOfTheZoneDoesNotApply(): void
    {
        $area = $this->zoneServedByTheModule();
        $other = $this->newCountry('XR', 'XRX', '991');
        (new CountryArea())->setAreaId($area->getId())->setCountryId($other->getId())->save($this->getPropelConnection());
        $this->rule($area, '20*', excluded: true, country: $other);

        self::assertTrue($this->isEligible('20090'), 'The exclusion was written for the other country.');
    }

    public function testAnUnknownPostalCodeHidesNoCarrier(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '75*');

        self::assertTrue($this->isEligible(null));
    }

    public function testTheGuardRefusesACartWhosePostalCodeTheZoneExcludes(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $cart = $this->guestCartShippedTo('20090');
        $cart->setDeliveryModuleId($this->module->getId())->save($this->getPropelConnection());

        $this->expectException(InvalidDeliveryException::class);
        $this->getService(CartGuard::class)->checkValidDelivery($cart);
    }

    public function testTheCheckoutListFollowsThePostalCodeOfTheDeliveryAddress(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $cart = $this->guestCartShippedTo('20090');

        self::assertNotContains($this->module->getId(), $this->offeredModuleIds($cart), 'A guest in Corsica is not offered the carrier.');

        $cart->getCartAddressRelatedByAddressDeliveryId()->setZipcode('69001')->save($this->getPropelConnection());

        self::assertContains($this->module->getId(), $this->offeredModuleIds($cart), 'Changing the postal code offers it again.');
    }

    public function testTheApiListOfCarriersFollowsThePostalCodeToo(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $facade = $this->getService(ShippingFacade::class);
        $ids = static fn (array $resources): array => array_map(static fn ($resource): int => (int) $resource->getId(), $resources);

        self::assertNotContains($this->module->getId(), $ids($facade->listValidMethodsAsResourceApi($this->guestCartShippedTo('20090'))));
        self::assertContains($this->module->getId(), $ids($facade->listValidMethodsAsResourceApi($this->guestCartShippedTo('69001'))));
    }

    public function testTheEstimateOfACartOffersNoCarrierItsAddressIsRefused(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $estimator = $this->getService(PostageEstimator::class);

        $corsica = $estimator->estimatePostageForCountry($this->guestCartShippedTo('20090'), $this->country);
        $lyon = $estimator->estimatePostageForCountry($this->guestCartShippedTo('69001'), $this->country);

        self::assertNull($corsica->getBestPostageAmount(), 'No carrier serves the address: no postage to estimate.');
        self::assertNotNull($lyon->getBestPostageAmount());
    }

    private function isEligible(?string $zipCode): bool
    {
        return $this->getService(DeliveryModuleEligibilityChecker::class)
            ->isEligible($this->module, $this->guestCartShippedTo($zipCode ?? '00000'), $this->country, null, $zipCode);
    }

    /**
     * @return list<int>
     */
    private function offeredModuleIds(Cart $cart): array
    {
        return array_map(
            static fn ($offer): int => (int) $offer->getDeliveryModule()->getId(),
            $this->getService(ShippingFacade::class)->listValidMethods($cart),
        );
    }

    private function zoneServedByTheModule(): Area
    {
        $area = (new Area())->setName('Postal code zone');
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($this->country->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($this->module->getId())->save($this->getPropelConnection());

        return $area;
    }

    private function rule(Area $area, string $pattern, bool $excluded = false, ?Country $country = null): void
    {
        (new AreaZipCodeRule())
            ->setAreaId($area->getId())
            ->setCountryId($country?->getId())
            ->setPattern($pattern)
            ->setExcluded($excluded)
            ->save($this->getPropelConnection());
    }

    private function guestCartShippedTo(string $zipCode): Cart
    {
        $address = $this->factory->cartAddress(null, $this->country, null, ['zipcode' => $zipCode]);
        $cart = $this->factory->cart();
        $cart->setAddressDeliveryId($address->getId())->save($this->getPropelConnection());
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $this->factory->cartItem($cart, $product);

        return $cart;
    }

    private function newCountry(string $alpha2, string $alpha3, string $isoCode): Country
    {
        return $this->factory->country(['isoalpha2' => $alpha2, 'isoalpha3' => $alpha3, 'isocode' => $isoCode]);
    }

    /**
     * Every carrier quotes every cart: the postal code rules alone decide.
     */
    private function quoteEveryCart(): void
    {
        $listener = static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true)->setPostage(new OrderPostage(5.0, 0.0, 'Test'));
            $event->stopPropagation();
        };
        $this->dispatcher()->addListener(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, $listener, 1024);
        $this->registeredListeners[] = [TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
