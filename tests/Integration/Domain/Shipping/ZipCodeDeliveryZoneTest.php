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

use ApiPlatform\Metadata\GetCollection;
use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Api\Bridge\Propel\Serializer\CartNormalizer;
use Thelia\Api\State\Provider\DeliveryModuleProvider;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Addressing\Service\AddressService;
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Checkout\Exception\InvalidDeliveryException;
use Thelia\Domain\Shipping\Service\DeliveryModuleEligibilityChecker;
use Thelia\Domain\Shipping\Service\PostageEstimator;
use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Model\Address;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\AreaZipCodeRule;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Delivery\RegistersDeliveryDateTestCarrier;

/**
 * A delivery zone narrowed down by postal codes: the carriers it brings are offered, quoted
 * and accepted only for the addresses whose postal code the zone accepts.
 */
final class ZipCodeDeliveryZoneTest extends IntegrationTestCase
{
    use RegistersDeliveryDateTestCarrier;

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

    public function testAnExclusionWinsOverAnInclusionOfTheSameZone(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '75016', excluded: true);
        $this->rule($area, '75*');

        self::assertFalse($this->isEligible('75016'));
        self::assertTrue($this->isEligible('75015'));
        self::assertFalse($this->isEligible('69001'), 'The inclusion limits the zone to Paris.');
    }

    public function testAnAlphanumericCodeIsComparedInItsNormalForm(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, 'K1A*', excluded: true);

        self::assertFalse($this->isEligible('k1a 0b1'));
        self::assertTrue($this->isEligible('M5V 3L9'));
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

    public function testThePostalCodeIsNamedWhenItIsWhyNoCarrierIsOffered(): void
    {
        $this->onlyTheModuleOfTheTestIsActive();
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $facade = $this->getService(ShippingFacade::class);

        self::assertSame('20 090', $facade->postalCodeWithoutCarrier($this->guestCartShippedTo('20 090')));
        self::assertNull($facade->postalCodeWithoutCarrier($this->guestCartShippedTo('69001')), 'A carrier is offered.');
    }

    public function testThePostalCodeIsNotBlamedForACarrierThatWouldNotQuoteTheCartAnyway(): void
    {
        $this->onlyTheModuleOfTheTestIsActive();
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(false);
            $event->stopPropagation();
        }, 2048);

        self::assertNull($this->getService(ShippingFacade::class)->postalCodeWithoutCarrier($this->guestCartShippedTo('20090')));
    }

    /**
     * One carrier serves the code but does not quote the cart, another would quote it but the
     * code is excluded from its zones: nothing is offered, and the postal code is why.
     */
    public function testThePostalCodeIsNamedWhenTheCarrierThatServesItDoesNotQuoteTheCart(): void
    {
        $this->onlyTheModuleOfTheTestIsActive();
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $notQuoting = $this->registerDeliveryDateTestCarrier(static::getContainer(), $this->getPropelConnection());
        $this->serveCountryWithCarrier($notQuoting, $this->country, $this->getPropelConnection());
        $notQuotingId = (int) $notQuoting->getId();
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event) use ($notQuotingId): void {
            if ($notQuotingId === (int) $event->getModule()->getModuleModel()->getId()) {
                $event->setValidModule(false);
                $event->stopPropagation();
            }
        }, 2048);
        $cart = $this->guestCartShippedTo('20090');

        self::assertSame([], $this->getService(ShippingFacade::class)->listValidMethods($cart), 'Nothing is offered.');
        self::assertSame('20090', $this->getService(ShippingFacade::class)->postalCodeWithoutCarrier($cart));
    }

    public function testThePostalCodeIsNotBlamedWhenNoCarrierServesTheCountry(): void
    {
        $this->onlyTheModuleOfTheTestIsActive();

        self::assertNull($this->getService(ShippingFacade::class)->postalCodeWithoutCarrier($this->guestCartShippedTo('20090')));
    }

    public function testTheFrontApiListsTheCarriersOfAGuestFromThePostalCodeOfItsCart(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $ids = static fn (array $resources): array => array_map(static fn ($resource): int => (int) $resource->getId(), $resources);

        self::assertNotContains($this->module->getId(), $ids($this->frontApiListFor($this->guestCartShippedTo('20090'))));
        self::assertContains($this->module->getId(), $ids($this->frontApiListFor($this->guestCartShippedTo('69001'))));
    }

    /**
     * A signed-in customer chose an address, then edited it from the account: the front API,
     * asked for the carriers of the cart, judges the cart's copy, as the guard will.
     */
    public function testTheFrontApiJudgesTheCartCopyOfACustomerAddressEditedSince(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $ids = static fn (array $resources): array => array_map(static fn ($resource): int => (int) $resource->getId(), $resources);

        $excludedSince = $this->factory->address($customer, $this->country, null, ['zipcode' => '69001']);
        $cart = $this->customerCartShippedTo($excludedSince);
        $excludedSince->setZipcode('20090')->save($this->getPropelConnection());
        self::assertContains($this->module->getId(), $ids($this->frontApiListFor($cart)), 'The copy is in Lyon.');

        $movedAbroad = $this->factory->address($customer, $this->country, null, ['zipcode' => '69001']);
        $abroadCart = $this->customerCartShippedTo($movedAbroad);
        $movedAbroad->setCountryId($this->newCountry('XZ', 'XZX', '989')->getId())->save($this->getPropelConnection());
        self::assertContains($this->module->getId(), $ids($this->frontApiListFor($abroadCart)), 'The copy is in the country of the zone.');

        self::assertNotContains(
            $this->module->getId(),
            $ids($this->frontApiListFor($cart, ['addressId' => (string) $excludedSince->getId()])),
            'An address the request names decides.',
        );
    }

    public function testAGuestIsListedTheCarriersOfTheZoneOfItsState(): void
    {
        $state = (new State())->setCountryId($this->country->getId())->setIsocode('XQ1')->setVisible(1);
        $state->save($this->getPropelConnection());
        $area = (new Area())->setName('State zone');
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($this->country->getId())->setStateId($state->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($this->module->getId())->save($this->getPropelConnection());
        $cart = $this->guestCartShippedTo('69001');
        $cart->getCartAddressRelatedByAddressDeliveryId()->setStateId($state->getId())->save($this->getPropelConnection());

        self::assertContains($this->module->getId(), $this->offeredModuleIds($cart), 'The list reads the state of the cart, as the guard does.');
    }

    public function testAListForACountryAloneReadsThePostalCodeOfTheCartAddressInIt(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $facade = $this->getService(ShippingFacade::class);
        $ids = static fn (array $offers): array => array_map(static fn ($offer): int => (int) $offer->getDeliveryModule()->getId(), $offers);

        self::assertNotContains($this->module->getId(), $ids($facade->listValidMethods($this->guestCartShippedTo('20090'), $this->country)));
        self::assertContains($this->module->getId(), $ids($facade->listValidMethods($this->guestCartShippedTo('69001'), $this->country)));
    }

    public function testTheApiPassingACountryAndAnAddressFollowsThePostalCodeOfTheAddress(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $address = $this->factory->address($customer, $this->country, null, ['zipcode' => '20090']);
        $cart = $this->customerCartShippedTo($this->factory->address($customer, $this->country, null, ['zipcode' => '69001']));
        $facade = $this->getService(ShippingFacade::class);
        $ids = static fn (array $resources): array => array_map(static fn ($resource): int => (int) $resource->getId(), $resources);

        self::assertNotContains(
            $this->module->getId(),
            $ids($facade->listValidMethodsAsResourceApi($cart, $this->country, null, $address->getId())),
            'The address named by the request decides, not the cart.',
        );
    }

    /**
     * GET /api/front/delivery_modules?addressId=N: the carriers offered must not tell whether
     * the postal code of an address of someone else is excluded.
     */
    public function testTheListReadsNoPostalCodeOfAnAddressOfAnotherCustomer(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $title = $this->factory->customerTitle();
        $cart = $this->customerCartShippedTo($this->factory->address($this->factory->customer($title), $this->country, null, ['zipcode' => '69001']));
        $someoneElses = $this->factory->address($this->factory->customer($title), $this->country, null, ['zipcode' => '20090']);
        $facade = $this->getService(ShippingFacade::class);
        $ids = static fn (array $resources): array => array_map(static fn ($resource): int => (int) $resource->getId(), $resources);

        self::assertContains(
            $this->module->getId(),
            $ids($facade->listValidMethodsAsResourceApi($cart, $this->country, null, $someoneElses->getId())),
            'The postal code of the cart decides, not the one of an address of someone else.',
        );
        self::assertContains($this->module->getId(), $ids($facade->listValidMethodsAsResourceApi($cart, null, null, $someoneElses->getId())));
    }

    /**
     * A guest row is reused by whoever types the same e-mail address: an earlier buyer's
     * address hangs off the row, and only the one the cart was copied from is this buyer's.
     */
    public function testAGuestReadsNoPostalCodeOfAnEarlierBuyerOfTheSameRow(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $guest = $this->factory->guestCustomer($this->factory->customerTitle());
        $earlierBuyers = $this->factory->address($guest, $this->country, null, ['zipcode' => '20090']);
        $cart = $this->customerCartShippedTo($this->factory->address($guest, $this->country, null, ['zipcode' => '69001']));
        $ids = static fn (array $resources): array => array_map(static fn ($resource): int => (int) $resource->getId(), $resources);

        self::assertContains(
            $this->module->getId(),
            $ids($this->getService(ShippingFacade::class)->listValidMethodsAsResourceApi($cart, $this->country, null, $earlierBuyers->getId())),
            'The postal code of an earlier buyer of the guest row decides nothing.',
        );
    }

    /**
     * The carriers are asked for a price with the address the request names only when it is
     * an address of the cart's customer: anyone else's never reaches a module.
     */
    public function testNoModuleIsHandedTheAddressOfAnotherCustomer(): void
    {
        $this->zoneServedByTheModule();
        $title = $this->factory->customerTitle();
        $mine = $this->factory->address($this->factory->customer($title), $this->country, null, ['zipcode' => '69001']);
        $someoneElses = $this->factory->address($this->factory->customer($title), $this->country, null, ['zipcode' => '20090']);
        $cart = $this->customerCartShippedTo($mine);
        $addressesHandedOver = [];
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event) use (&$addressesHandedOver): void {
            $addressesHandedOver[] = $event->getAddress()?->getId();
        }, 4096);
        $facade = $this->getService(ShippingFacade::class);
        $ids = static fn (array $resources): array => array_map(static fn ($resource): int => (int) $resource->getId(), $resources);

        $named = $ids($facade->listValidMethodsAsResourceApi($cart, null, null, $someoneElses->getId()));

        self::assertNotContains($someoneElses->getId(), $addressesHandedOver, 'A module was handed the address of another customer.');
        self::assertContains($mine->getId(), $addressesHandedOver);
        self::assertSame($ids($facade->listValidMethodsAsResourceApi($cart)), $named, 'The list is the one of the cart.');
    }

    public function testAnAddressInAnotherCountryLeavesThePostalCodeOfTheCartToSpeak(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $abroad = $this->factory->address($customer, $this->newCountry('XN', 'XNX', '988'), null, ['zipcode' => '69001']);
        $cart = $this->customerCartShippedTo($this->factory->address($customer, $this->country, null, ['zipcode' => '20090']));
        $ids = static fn (array $resources): array => array_map(static fn ($resource): int => (int) $resource->getId(), $resources);

        self::assertNotContains(
            $this->module->getId(),
            $ids($this->getService(ShippingFacade::class)->listValidMethodsAsResourceApi($cart, $this->country, null, $abroad->getId())),
            'The cart is in Corsica: an address abroad does not make the code unknown.',
        );
    }

    public function testAGuestCartReadsNoPostalCodeOfACustomerAddress(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $address = $this->factory->address($this->factory->customer($this->factory->customerTitle()), $this->country, null, ['zipcode' => '20090']);
        $ids = static fn (array $resources): array => array_map(static fn ($resource): int => (int) $resource->getId(), $resources);

        self::assertContains(
            $this->module->getId(),
            $ids($this->getService(ShippingFacade::class)->listValidMethodsAsResourceApi($this->guestCartShippedTo('69001'), $this->country, null, $address->getId())),
        );
    }

    /**
     * The customer address moved abroad after it was chosen: the cart's copy, which the order
     * leaves for and the guard judges, still decides the country, the state and the postal code.
     */
    public function testTheListJudgesTheCartCopyOfACustomerAddressThatMovedSince(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $address = $this->factory->address($customer, $this->country, null, ['zipcode' => '69001']);
        $cart = $this->customerCartShippedTo($address);
        $abroad = $this->newCountry('XS', 'XSX', '992');
        $address->setCountryId($abroad->getId())->setZipcode('20090')->save($this->getPropelConnection());

        self::assertContains($this->module->getId(), $this->offeredModuleIds($cart), 'The list reads the copy, as the guard does.');

        $cart->setDeliveryModuleId($this->module->getId())->save($this->getPropelConnection());
        $this->getService(CartGuard::class)->checkValidDelivery($cart);
    }

    /**
     * A checkout page asks for the carriers of a zone several times over: its rules are read
     * once, until a rule is written.
     */
    public function testTheRulesOfAZoneAreReadOnceUntilARuleIsWritten(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        [$country, $module] = [$this->country, $this->module];
        $query = static fn (?string $zipCode): ?AreaDeliveryModule => AreaDeliveryModuleQuery::create()
            ->findByCountryAndModule($country, $module, null, $zipCode);

        $reads = $this->countRuleReads(static function () use ($query): void {
            self::assertNull($query('20090'));
            self::assertNull($query('20090'));
            self::assertNotNull($query('69001'));
        });
        self::assertSame(1, $reads, 'The rules of the zone are read once.');

        self::assertSame(0, $this->countRuleReads(static fn () => $query(null)), 'Without a postal code no rule is read.');

        $inclusion = $this->rule($area, '75*');

        self::assertSame(1, $this->countRuleReads(static fn () => self::assertNull($query('69001'), 'The rule just written applies.')));

        $inclusion->delete($this->getPropelConnection());

        self::assertSame(1, $this->countRuleReads(static fn () => self::assertNotNull($query('69001'), 'The rule just deleted no longer applies.')));
    }

    public function testTheListsJudgeEachCarrierOnceAndAskNoPriceOfACarrierTheAddressIsRefused(): void
    {
        $this->onlyTheModuleOfTheTestIsActive();
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $facade = $this->getService(ShippingFacade::class);
        $moduleId = (int) $this->module->getId();
        $quotes = 0;
        $countQuotes = static function (DeliveryPostageEvent $event) use (&$quotes, $moduleId): void {
            if ($moduleId === (int) $event->getModule()->getModuleModel()->getId()) {
                ++$quotes;
            }
        };
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, $countQuotes, 2048);
        $corsica = $this->guestCartShippedTo('20090');
        $lyon = $this->guestCartShippedTo('69001');

        self::assertSame([], $facade->listValidMethodsAsResourceApi($corsica));
        self::assertSame([], $facade->listValidMethods($corsica));
        self::assertSame(0, $quotes, 'A carrier the address is refused is not asked for a price.');

        self::assertSame(1, $this->countStatementsOn('area_delivery_module', static fn () => self::assertCount(1, $facade->listValidMethods($lyon))), 'The carrier is judged once.');
    }

    public function testTheDeliveryLoopFollowsThePostalCodeOfItsAddress(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $corsica = $this->factory->address($customer, $this->country, null, ['zipcode' => '20090']);
        $lyon = $this->factory->address($customer, $this->country, null, ['zipcode' => '69001']);
        $cart = $this->customerCartShippedTo($lyon);

        self::assertNotContains((int) $this->module->getId(), $this->deliveryLoopModuleIds($cart, ['address' => $corsica->getId()]));
        self::assertContains((int) $this->module->getId(), $this->deliveryLoopModuleIds($cart, ['address' => $lyon->getId()]));
    }

    public function testTheDeliveryLoopReadsNoPostalCodeForAnotherCountryThanTheOneOfItsAddress(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $abroad = $this->newCountry('XT', 'XTX', '993');
        $customer = $this->factory->customer($this->factory->customerTitle());
        $address = $this->factory->address($customer, $abroad, null, ['zipcode' => '20090']);

        self::assertContains(
            (int) $this->module->getId(),
            $this->deliveryLoopModuleIds($this->customerCartShippedTo($address), ['address' => $address->getId(), 'country' => $this->country->getId()]),
            'The postal code of an address abroad says nothing of the country listed.',
        );
    }

    /**
     * The API cart payload of a cart that has not chosen its carrier yet carries an estimate:
     * read with the postal code of the cart's address, as the checkout will judge it.
     */
    public function testTheCartPayloadEstimatesWithThePostalCodeOfTheCartAddress(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $normalizer = $this->getService(CartNormalizer::class);
        $deliveryCostOf = new \ReflectionMethod(CartNormalizer::class, 'deliveryCostOf');

        [$corsica] = $deliveryCostOf->invoke($normalizer, $this->guestCartShippedTo('20090'), $this->country, null, false);
        [$lyon] = $deliveryCostOf->invoke($normalizer, $this->guestCartShippedTo('69001'), $this->country, null, false);

        self::assertNull($corsica, 'No carrier serves the address: no delivery cost to show.');
        self::assertNotNull($lyon);
    }

    public function testTheEstimateForAnotherCountryReadsNoPostalCodeOfTheCartAddress(): void
    {
        $area = $this->zoneServedByTheModule();
        $this->rule($area, '20*', excluded: true);
        $abroad = $this->newCountry('XU', 'XUX', '994');
        $address = $this->factory->cartAddress(null, $abroad, null, ['zipcode' => '20090']);
        $cart = $this->guestCartShippedTo('20090');
        $cart->setAddressDeliveryId($address->getId())->save($this->getPropelConnection());

        self::assertNotNull(
            $this->getService(PostageEstimator::class)->estimatePostageForCountry($cart, $this->country)->getBestPostageAmount(),
            'The postal code of an address abroad says nothing of the country estimated for.',
        );
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

    /**
     * The queries on the postal code rules the callable runs.
     */
    private function countRuleReads(callable $evaluate): int
    {
        return $this->countStatementsOn('area_zip_code_rule', $evaluate);
    }

    /**
     * The queries naming the table the callable runs.
     */
    private function countStatementsOn(string $table, callable $evaluate): int
    {
        $connection = $this->getPropelConnection();
        $logger = $connection->getLogger();
        $statements = new class extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->lines[] = (string) $message;
            }
        };
        $connection->setLogger($statements);
        $connection->useDebug(true);

        try {
            $evaluate();
        } finally {
            $connection->useDebug(false);
            $connection->setLogger($logger);
        }

        return \count(array_filter($statements->lines, static fn (string $line): bool => str_contains($line, $table)));
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

    /**
     * The seeded carriers serve no zone of the test country, but deactivating them keeps
     * the test about the one carrier it configures.
     */
    private function onlyTheModuleOfTheTestIsActive(): void
    {
        ModuleQuery::create()
            ->filterByType(2)
            ->filterById($this->module->getId(), \Propel\Runtime\ActiveQuery\Criteria::NOT_EQUAL)
            ->update(['Activate' => 0], $this->getPropelConnection());
    }

    /**
     * What GET /api/front/delivery_modules answers for a session holding the cart.
     *
     * @param array<string, string> $query
     *
     * @return list<mixed>
     */
    private function frontApiListFor(Cart $cart, array $query = []): array
    {
        $session = new Session(new MockArraySessionStorage());
        // The cart of a customer is the session's only once the customer is signed in.
        if (null !== $cart->getCustomer()) {
            $session->setCustomerUser($cart->getCustomer());
        }
        $session->setSessionCart($cart);
        $request = new Request($query);
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new DeliveryModuleProvider(
            $requestStack,
            $session,
            $this->getService(SecurityContext::class),
            $this->getService(AddressService::class),
            $this->dispatcher(),
            $this->getService(ShippingFacade::class),
        );

        return array_values((array) $provider->provide(new GetCollection()));
    }

    private function zoneServedByTheModule(): Area
    {
        $area = (new Area())->setName('Postal code zone');
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($this->country->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($this->module->getId())->save($this->getPropelConnection());

        return $area;
    }

    private function rule(Area $area, string $pattern, bool $excluded = false, ?Country $country = null): AreaZipCodeRule
    {
        $rule = (new AreaZipCodeRule())
            ->setAreaId($area->getId())
            ->setCountryId($country?->getId())
            ->setPattern($pattern)
            ->setExcluded($excluded);
        $rule->save($this->getPropelConnection());

        return $rule;
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

    /**
     * The carriers the `delivery` loop lists for the cart of the session.
     *
     * @param array<string, mixed> $arguments
     *
     * @return list<int>
     */
    private function deliveryLoopModuleIds(Cart $cart, array $arguments): array
    {
        $session = $this->getService(RequestStack::class)->getMainRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);
        $session->setSessionCart($cart);

        try {
            $ids = [];

            foreach ($this->getService(LoopExecutor::class)->execute('delivery', $arguments) as $row) {
                $ids[] = (int) $row->get('ID');
            }

            return $ids;
        } finally {
            $session->setSessionCart(null);
        }
    }

    /**
     * The cart of a signed-in customer, delivered to its copy of one of their addresses.
     */
    private function customerCartShippedTo(Address $address): Cart
    {
        $copy = $this->factory->cartAddress($address, $address->getCountry());
        $cart = $this->factory->cart($address->getCustomer());
        $cart->setAddressDeliveryId($copy->getId())->save($this->getPropelConnection());
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
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true)->setPostage(new OrderPostage(5.0, 0.0, 'Test'));
            $event->stopPropagation();
        }, 1024);
    }

    private function listen(string $eventName, callable $listener, int $priority): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
