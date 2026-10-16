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
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Api\Bridge\Propel\Event\DeliveryModuleOptionEvent;
use Thelia\Api\Resource\DeliveryModule as DeliveryModuleResource;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Api\State\Provider\DeliveryModuleProvider;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Addressing\Service\AddressService;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Product\ProductVisibilityRuleInterface;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\Service\CheckoutPlacementService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Sale\ReservedSaleProductRule;
use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Domain\Shipping\DeliveryDate\Exception\InvalidDeliveryDateSettingsException;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateSettings;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryEstimateCalendar;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryTimeEstimator;
use Thelia\Domain\Shipping\DeliveryDate\Service\ProductDeliveryEstimator;
use Thelia\Domain\Shipping\Service\DeliveryOptionsProvider;
use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Log\Destination\TlogDestinationText;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\Product;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Delivery\DeliveryDateTestCarrier;
use Thelia\Tests\Support\Delivery\RegistersDeliveryDateTestCarrier;

/**
 * The delivery a carrier announces from the delays the merchant set: on the delivery step,
 * on the order once placed, and on a product page.
 *
 * The shop is open every day and has no cutoff unless a test says otherwise, so that the
 * expected days only depend on today.
 */
final class DeliveryEstimateTest extends IntegrationTestCase
{
    use RegistersDeliveryDateTestCarrier;

    private FixtureFactory $factory;

    private Module $carrier;

    private string $shopClosedWeekdaysBefore;

    private string $cutoffBefore;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    /** What the test carrier writes in its option, before the core fills it. */
    private ?string $moduleMinimumDate = null;

    private ?string $moduleMaximumDate = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->carrier = $this->registerDeliveryDateTestCarrier(static::getContainer(), $this->getPropelConnection());
        $this->shopClosedWeekdaysBefore = (string) ConfigQuery::read('delivery_closed_weekdays', '');
        $this->cutoffBefore = (string) ConfigQuery::read(DeliveryTimeEstimator::CUTOFF_CONFIG_NAME, '');
        ConfigQuery::write('delivery_closed_weekdays', '');
        ConfigQuery::write(DeliveryTimeEstimator::CUTOFF_CONFIG_NAME, '');
        $this->turnMandatoryConsentsOff();

        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_OPTIONS, function (DeliveryModuleOptionEvent $event): void {
            if ($event->getModule()->getId() !== $this->carrier->getId()) {
                return;
            }

            $event->appendDeliveryModuleOptions(
                (new DeliveryModuleOption())
                    ->setCode('estimate-test')
                    ->setMinimumDeliveryDate($this->moduleMinimumDate)
                    ->setMaximumDeliveryDate($this->moduleMaximumDate),
            );
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        ConfigQuery::write('delivery_closed_weekdays', $this->shopClosedWeekdaysBefore);
        ConfigQuery::write(DeliveryTimeEstimator::CUTOFF_CONFIG_NAME, $this->cutoffBefore);
        DeliveryDateTestCarrier::$acceptedModes = [DeliveryDateChoiceMode::Date, DeliveryDateChoiceMode::Slot];
        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    public function testACarrierWithoutDelaysAnnouncesNoDate(): void
    {
        [$cart] = $this->cartReadyToPay();

        self::assertSame([null, null], $this->announcedFor($cart));
    }

    /**
     * Recette 1 and 5: the option of the carrier carries its own interval.
     */
    public function testTheOptionOfACarrierCarriesTheIntervalOfItsDelays(): void
    {
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        [$cart] = $this->cartReadyToPay();

        self::assertSame([$this->day(2), $this->day(3)], $this->announcedFor($cart));
    }

    public function testZeroDaysAreDelaysLikeAnyOther(): void
    {
        $this->settings()->saveDeliveryDelays($this->carrier, 0, 0, 0);
        [$cart] = $this->cartReadyToPay();

        self::assertSame([$this->day(0), $this->day(0)], $this->announcedFor($cart));
    }

    /**
     * Recette 2: an order taken at or after the cutoff is prepared the next day.
     */
    public function testAnOrderAfterTheCutoffIsAnnouncedOneDayLater(): void
    {
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        $this->settings()->saveShopCutoffTime('00:00');
        [$cart] = $this->cartReadyToPay();

        self::assertSame([$this->day(3), $this->day(4)], $this->announcedFor($cart));
    }

    /**
     * Recette 4: the slowest product of the cart sets the extra preparation.
     */
    public function testTheSlowestProductOfTheCartStretchesThePreparation(): void
    {
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        [$cart] = $this->cartReadyToPay();
        $this->factory->cartItem($cart, $this->product(['availabilityDelayDays' => 5]));
        $this->factory->cartItem($cart, $this->product(['availabilityDelayDays' => 2]));

        self::assertSame([$this->day(7), $this->day(8)], $this->announcedFor($cart));
    }

    /**
     * Zero days of preparation ship like one: the days the goods need count from there, they
     * are not swallowed by it.
     */
    public function testTheDaysOfTheProductCountAfterAPreparationOfZeroDays(): void
    {
        $this->settings()->saveDeliveryDelays($this->carrier, 0, 2, 3);
        [$cart] = $this->cartReadyToPay();
        $this->factory->cartItem($cart, $this->product(['availabilityDelayDays' => 2]));

        self::assertSame([$this->day(4), $this->day(5)], $this->announcedFor($cart));
    }

    public function testADelayBeyondTheHorizonIsCountedAsTheHorizon(): void
    {
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 0, 0);
        [$cart] = $this->cartReadyToPay();
        $product = $this->product();
        $product->setAvailabilityDelayDays(2000000000)->save($this->getPropelConnection());
        $this->factory->cartItem($cart, $product);

        $started = microtime(true);
        $estimate = $this->getService(DeliveryTimeEstimator::class)->estimateForCart($this->carrier, $cart);

        self::assertLessThan(2.0, microtime(true) - $started, 'The estimate walked the whole delay.');
        self::assertNotNull($estimate);
        self::assertSame($this->day(1 + 366 - 1), $estimate->earliestDay());
    }

    /**
     * A carrier the buyer picks a day from shows its calendar: no estimate beside it, on the
     * delivery step nor on a product page.
     */
    public function testACarrierThatLetsTheBuyerPickADayAnnouncesNoEstimate(): void
    {
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 1, 10, []);
        [$cart] = $this->cartReadyToPay();
        $defaultCountry = CountryQuery::create()->findOneByByDefault(1);
        self::assertInstanceOf(Country::class, $defaultCountry);
        $this->serveCountryWithCarrier($this->carrier, $defaultCountry, $this->getPropelConnection());

        self::assertSame([null, null], $this->announcedFor($cart));
        self::assertNull($this->getService(ShippingFacade::class)->estimateProductDelivery($this->product()));
    }

    public function testDatesTheModuleGaveItselfAreKept(): void
    {
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        [$cart] = $this->cartReadyToPay();

        $this->moduleMinimumDate = '2030-01-02';
        $this->moduleMaximumDate = '2030-01-04';
        self::assertSame(['2030-01-02', '2030-01-04'], $this->announcedFor($cart));

        // An empty string is what some modules write for "unknown": it is no date.
        $this->moduleMinimumDate = '';
        $this->moduleMaximumDate = '';
        self::assertSame([$this->day(2), $this->day(3)], $this->announcedFor($cart));
    }

    /**
     * Dev check: the interval announced at checkout is kept on the order, and does not move
     * when the delays of the carrier change afterwards.
     */
    public function testTheIntervalIsFrozenOnTheOrder(): void
    {
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        [$cart, $customer] = $this->cartReadyToPay();

        $orderId = $this->place($cart, $customer);
        $this->settings()->saveDeliveryDelays($this->carrier, 5, 10, 12);
        $order = OrderQuery::create()->findPk($orderId);

        self::assertNotNull($order);
        self::assertSame($this->day(2), $order->getEstimatedDeliveryFromDay());
        self::assertSame($this->day(3), $order->getEstimatedDeliveryToDay());

        // Recette 7: the confirmation e-mail reads the order loop.
        $row = null;
        foreach ($this->getService(LoopExecutor::class)->execute('order', ['id' => $orderId, 'customer' => $customer->getId()]) as $result) {
            $row = $result->getVarVal();
        }
        self::assertIsArray($row);
        self::assertSame($this->day(2), $row['ESTIMATED_DELIVERY_FROM']);
        self::assertSame($this->day(3), $row['ESTIMATED_DELIVERY_TO']);
    }

    public function testAnOrderWithoutDelaysOrWithAPickedDayCarriesNoEstimate(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $withoutDelays = OrderQuery::create()->findPk($this->place($cart, $customer));

        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 1, 10, []);
        [$cart, $customer] = $this->cartReadyToPay();
        $this->getService(CartFacade::class)->chooseDeliveryDate($cart, $this->day(4));
        $withADay = OrderQuery::create()->findPk($this->place($cart, $customer));

        self::assertNotNull($withoutDelays);
        self::assertNull($withoutDelays->getEstimatedDeliveryFromDay());
        self::assertNull($withoutDelays->getEstimatedDeliveryToDay());
        self::assertNotNull($withADay);
        self::assertSame($this->day(4), $withADay->getDeliveryDay());
        self::assertNull($withADay->getEstimatedDeliveryFromDay());
    }

    /**
     * A day left on the cart by a carrier the merchant has since stopped offering days for is
     * dropped from the order: the order then gets the estimate, not nothing.
     */
    public function testAnOrderWhoseDayWasDroppedGetsTheEstimate(): void
    {
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 1, 10, []);
        [$cart, $customer] = $this->cartReadyToPay();
        $this->getService(CartFacade::class)->chooseDeliveryDate($cart, $this->day(4));
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::None, 1, 10, []);

        $order = OrderQuery::create()->findPk($this->place($cart, $customer));

        self::assertNotNull($order);
        self::assertNull($order->getDeliveryDay());
        self::assertSame([$this->day(2), $this->day(3)], [$order->getEstimatedDeliveryFromDay(), $order->getEstimatedDeliveryToDay()]);
    }

    /**
     * Recette 6: the product page names the soonest delivery and the country it holds for.
     */
    public function testAProductPageAnnouncesTheSoonestCarrierForTheDefaultCountry(): void
    {
        $defaultCountry = CountryQuery::create()->findOneByByDefault(1);
        self::assertInstanceOf(Country::class, $defaultCountry);
        $this->serveCountryWithCarrier($this->carrier, $defaultCountry, $this->getPropelConnection());
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        $product = $this->product(['availabilityDelayDays' => 1]);

        $estimate = $this->getService(ShippingFacade::class)->estimateProductDelivery($product);

        self::assertNotNull($estimate);
        self::assertSame($defaultCountry->getId(), $estimate->country->getId());
        self::assertSame($this->carrier->getId(), $estimate->carrier->getId());
        self::assertSame([$this->day(3), $this->day(4)], [$estimate->estimate->earliestDay(), $estimate->estimate->latestDay()]);

        $byId = $this->getService(ShippingFacade::class)->estimateProductDeliveryById((int) $product->getId());
        self::assertNotNull($byId);
        self::assertSame($this->day(3), $byId->estimate->earliestDay());
        self::assertNull($this->getService(ShippingFacade::class)->estimateProductDeliveryById(0));
    }

    public function testAVirtualProductAnnouncesNoDelivery(): void
    {
        $defaultCountry = CountryQuery::create()->findOneByByDefault(1);
        self::assertInstanceOf(Country::class, $defaultCountry);
        $this->serveCountryWithCarrier($this->carrier, $defaultCountry, $this->getPropelConnection());
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        $product = $this->product();
        $product->setVirtual(1)->save($this->getPropelConnection());

        self::assertNull($this->getService(ShippingFacade::class)->estimateProductDelivery($product));
    }

    public function testTheDelaysOfACarrierMustHoldTogether(): void
    {
        foreach ([[null, 2, null], [null, null, 2], [1, null, null], [-1, 1, 2], [1, 3, 2], [1, 1, 400]] as [$preparation, $min, $max]) {
            try {
                $this->settings()->saveDeliveryDelays($this->carrier, $preparation, $min, $max);
                self::fail(\sprintf('Delays %s, %s, %s were accepted.', var_export($preparation, true), var_export($min, true), var_export($max, true)));
            } catch (InvalidDeliveryDateSettingsException) {
            }
        }

        // Taking the estimate off is all three null.
        $rule = $this->settings()->saveDeliveryDelays($this->carrier, null, null, null);
        self::assertFalse(DeliveryTimeEstimator::announcesAnEstimate($rule));
    }

    /**
     * Dev check: GET /api/front/delivery_modules lists the carrier with the interval on the
     * two dates its options already carried, for the cart of the session.
     */
    public function testTheFrontApiListsTheIntervalOnTheOptionsOfTheCarrier(): void
    {
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        [$cart] = $this->cartReadyToPay();
        $defaultCountry = CountryQuery::create()->findOneByByDefault(1);
        self::assertInstanceOf(Country::class, $defaultCountry);
        $this->serveCountryWithCarrier($this->carrier, $defaultCountry, $this->getPropelConnection());

        $session = new Session(new MockArraySessionStorage());
        $session->setSessionCart($cart);
        $request = new Request();
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

        $listed = null;
        foreach ((array) $provider->provide(new GetCollection()) as $module) {
            if ($module instanceof DeliveryModuleResource && $module->getId() === $this->carrier->getId()) {
                $listed = $module;
            }
        }

        self::assertNotNull($listed, 'The front API lists the carrier.');
        $option = $listed->getOptions()[0] ?? null;
        self::assertInstanceOf(DeliveryModuleOption::class, $option);
        self::assertSame([$this->day(2), $this->day(3)], [$option->getMinimumDeliveryDate(), $option->getMaximumDeliveryDate()]);
    }

    /**
     * Dev check: a command that writes an e-mail has no request; the estimate is the same.
     */
    public function testTheEstimateNeedsNoRequest(): void
    {
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        [$cart] = $this->cartReadyToPay();
        $requestStack = $this->getService(RequestStack::class);
        $popped = [];

        while (null !== $request = $requestStack->pop()) {
            $popped[] = $request;
        }

        try {
            $estimate = $this->getService(DeliveryTimeEstimator::class)->estimateForCart($this->carrier, $cart);
        } finally {
            foreach (array_reverse($popped) as $request) {
                $requestStack->push($request);
            }
        }

        self::assertNotNull($estimate);
        self::assertSame([$this->day(2), $this->day(3)], [$estimate->earliestDay(), $estimate->latestDay()]);
    }

    /**
     * The delivery step estimates every carrier it lists: past the first one, which loads
     * the delays and the calendar, another carrier costs no query.
     */
    public function testEstimatingMoreCarriersCostsNoMoreQueries(): void
    {
        [$cart] = $this->cartReadyToPay();
        $carriers = ModuleQuery::create()->limit(4)->find($this->getPropelConnection())->getData();
        self::assertCount(4, $carriers);
        foreach ($carriers as $carrier) {
            $this->settings()->saveDeliveryDelays($carrier, 1, 2, 3);
        }
        $estimator = $this->getService(DeliveryTimeEstimator::class);
        self::assertNotNull($estimator->estimateForCart($carriers[0], $cart));

        $connection = $this->getPropelConnection();
        $connection->useDebug(true);

        try {
            $before = $connection->getQueryCount();
            foreach (\array_slice($carriers, 1) as $carrier) {
                self::assertNotNull($estimator->estimateForCart($carrier, $cart));
            }
            $queries = $connection->getQueryCount() - $before;
        } finally {
            $connection->useDebug(false);
        }

        self::assertSame(0, $queries, \sprintf('Three more carriers cost %d queries.', $queries));
    }

    /**
     * The same holds for carriers that let the buyer pick a day: deciding they announce no
     * estimate reads their rule from the same cache, not from the database.
     */
    public function testCarriersThatLetTheBuyerPickADayCostNoMoreQueriesEither(): void
    {
        [$cart] = $this->cartReadyToPay();
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 1, 10, []);
        $others = ModuleQuery::create()->filterById($this->carrier->getId(), Criteria::NOT_EQUAL)->limit(3)->find($this->getPropelConnection())->getData();
        foreach ($others as $other) {
            $this->settings()->saveDeliveryDelays($other, 1, 2, 3);
        }
        $estimator = $this->getService(DeliveryTimeEstimator::class);
        // Warmed by a carrier that counts: the one that lets the buyer pick a day stops before the closures.
        $estimator->estimateForCart(array_shift($others), $cart);

        $connection = $this->getPropelConnection();
        $connection->useDebug(true);

        try {
            $before = $connection->getQueryCount();
            self::assertNull($estimator->estimateForCart($this->carrier, $cart));
            foreach ($others as $other) {
                $estimator->estimateForCart($other, $cart);
            }
            $queries = $connection->getQueryCount() - $before;
        } finally {
            $connection->useDebug(false);
        }

        self::assertSame(0, $queries, \sprintf('Three more estimates cost %d queries.', $queries));
    }

    /**
     * An estimate that cannot be counted leaves the delivery step and the order without one:
     * it never refuses the order.
     */
    public function testAnEstimateThatFailsLeavesTheOrderPlacedWithoutOne(): void
    {
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        [$cart, $customer] = $this->cartReadyToPay();
        $connection = $this->getPropelConnection();
        $this->getService(DeliveryEstimateCalendar::class)->reset();

        // Masks the closures table for this connection, without an implicit commit.
        $connection->exec('CREATE TEMPORARY TABLE `delivery_closure` (id INT)');

        try {
            self::assertSame([null, null], $this->announcedFor($cart));
            $order = OrderQuery::create()->findPk($this->place($cart, $customer));
        } finally {
            $connection->exec('DROP TEMPORARY TABLE `delivery_closure`');
        }

        self::assertNotNull($order);
        self::assertNull($order->getEstimatedDeliveryFromDay());
    }

    public function testAnOfflineProductHasNoEstimateById(): void
    {
        $defaultCountry = CountryQuery::create()->findOneByByDefault(1);
        self::assertInstanceOf(Country::class, $defaultCountry);
        $this->serveCountryWithCarrier($this->carrier, $defaultCountry, $this->getPropelConnection());
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        $product = $this->product();
        $product->setVisible(0)->save($this->getPropelConnection());

        self::assertNotNull($this->getService(ShippingFacade::class)->estimateProductDelivery($product));
        self::assertNull($this->getService(ShippingFacade::class)->estimateProductDeliveryById((int) $product->getId()));
    }

    /**
     * A product a visibility rule keeps out of the visitor's catalogue (a private sale, a
     * reserved range) has no estimate by id either.
     */
    public function testAProductOutOfTheVisitorsCatalogueHasNoEstimateById(): void
    {
        $defaultCountry = CountryQuery::create()->findOneByByDefault(1);
        self::assertInstanceOf(Country::class, $defaultCountry);
        $this->serveCountryWithCarrier($this->carrier, $defaultCountry, $this->getPropelConnection());
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        $hidden = (int) $this->product()->getId();

        $rule = new class($hidden) implements ProductVisibilityRuleInterface {
            public function __construct(private int $hiddenProductId)
            {
            }

            public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
            {
                return \sprintf('%s <> %d', $productIdColumn, $this->hiddenProductId);
            }
        };
        $estimator = new ProductDeliveryEstimator(
            $this->getService(DeliveryTimeEstimator::class),
            new ProductVisibility($this->getService(ReservedSaleProductRule::class), $this->getService(CurrentCustomerProvider::class), [$rule]),
        );

        self::assertNull($estimator->estimateById($hidden));
        self::assertNotNull($estimator->estimateById((int) $this->product()->getId()));
    }

    public function testACarrierWithoutCodeIsNamedByItsIdInTheLog(): void
    {
        $defaultCountry = CountryQuery::create()->findOneByByDefault(1);
        self::assertInstanceOf(Country::class, $defaultCountry);
        $connection = $this->getPropelConnection();
        $this->serveCountryWithCarrier($this->carrier, $defaultCountry, $connection);
        $product = $this->product();
        $this->carrier->setCode('')->save($connection);
        $connection->exec('CREATE TEMPORARY TABLE `area_delivery_module` (id INT)');

        $logged = $this->loggedWhile(function () use ($product): void {
            $this->getService(ShippingFacade::class)->estimateProductDelivery($product);
        }, static fn () => $connection->exec('DROP TEMPORARY TABLE `area_delivery_module`'));

        self::assertMatchesRegularExpression('/The zones of carrier\\(s\\) [^\\n]*#'.$this->carrier->getId().'\\b/', $logged);
    }

    public function testAVisibilityRuleThatFailsAnnouncesNothingAndBreaksNothing(): void
    {
        $broken = new class implements ProductVisibilityRuleInterface {
            public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
            {
                return '';
            }
        };
        $estimator = new ProductDeliveryEstimator(
            $this->getService(DeliveryTimeEstimator::class),
            new ProductVisibility($this->getService(ReservedSaleProductRule::class), $this->getService(CurrentCustomerProvider::class), [$broken]),
        );

        $productId = (int) $this->product()->getId();
        $logged = $this->loggedWhile(static function () use ($estimator, $productId, &$estimate): void {
            $estimate = $estimator->estimateById($productId);
        }, static fn () => null);

        self::assertNull($estimate);
        self::assertStringContainsString(\sprintf('The product %d could not be read for a delivery estimate', $productId), $logged);
        self::assertStringNotContainsString('The zones of carrier(s)', $logged);
        self::assertStringNotContainsString('The delivery of product', $logged);
    }

    /**
     * A product page estimate whose carrier zones cannot be read announces nothing, whether
     * the theme holds the product or its id: the page shows.
     */
    public function testAProductEstimateThatFailsAnnouncesNothingAndBreaksNothing(): void
    {
        $defaultCountry = CountryQuery::create()->findOneByByDefault(1);
        self::assertInstanceOf(Country::class, $defaultCountry);
        $this->serveCountryWithCarrier($this->carrier, $defaultCountry, $this->getPropelConnection());
        $this->settings()->saveDeliveryDelays($this->carrier, 1, 2, 3);
        $product = $this->product();
        $connection = $this->getPropelConnection();
        self::assertGreaterThanOrEqual(2, ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->count($connection), 'Several carriers, or one entry per carrier and one per estimate cannot be told apart.');

        // Masks the carrier zones for this connection, without an implicit commit.
        $connection->exec('CREATE TEMPORARY TABLE `area_delivery_module` (id INT)');

        $logged = $this->loggedWhile(function () use (&$byProduct, &$byId, $product): void {
            $byProduct = $this->getService(ShippingFacade::class)->estimateProductDelivery($product);
            $byId = $this->getService(ShippingFacade::class)->estimateProductDeliveryById((int) $product->getId());
        }, static fn () => $connection->exec('DROP TEMPORARY TABLE `area_delivery_module`'));

        self::assertNull($byProduct);
        self::assertNull($byId);
        // Each carrier was left out on its own, the estimate was not abandoned as a whole.
        self::assertMatchesRegularExpression('/The zones of carrier\\(s\\) [^,\\n]+, [^\\n]+ could not be read/', $logged, 'Every carrier is named in the one entry.');
        self::assertStringNotContainsString('The delivery of product', $logged);
        self::assertStringNotContainsString('The product ', $logged);
        self::assertSame(2, substr_count($logged, 'The zones of carrier(s) '), 'One entry per estimate, not one per carrier.');
        // Each entry names the carriers by their code, never by an id: only the line naming
        // them is read, the trace below it numbers its frames with # too.
        self::assertSame(2, preg_match_all('/^.*The zones of carrier\\(s\\) [^\\n]*\\b'.preg_quote(DeliveryDateTestCarrier::getModuleCode(), '/').'\\b[^\\n]* could not be read/m', $logged));
        self::assertDoesNotMatchRegularExpression('/The zones of carrier\\(s\\) [^\\n]*#\\d/', $logged);
    }

    public function testTheCutoffMustBeAnHour(): void
    {
        $this->expectException(InvalidDeliveryDateSettingsException::class);

        $this->settings()->saveShopCutoffTime('2pm');
    }

    /**
     * What the shop log receives while the action runs: Tlog reads its configuration from the
     * database, so a bare text logger stands in for it, then the shop's comes back. The bare
     * logger is set the way Tlog::out() reads it (level, files, prefix, destinations): a field
     * out() comes to need from init() has to be set here too.
     */
    private function loggedWhile(callable $action, callable $finally): string
    {
        $instance = new \ReflectionProperty(Tlog::class, 'instance');
        $shopLogger = $instance->getValue();
        $instance->setValue(null, (new \ReflectionClass(Tlog::class))->newInstanceWithoutConstructor()
            ->setLevel(Tlog::DEBUG)
            ->setFiles('*')
            ->setPrefix('#LEVEL: ')
            ->setDestinations(TlogDestinationText::class));
        ob_start();

        try {
            $action();
        } finally {
            $logged = (string) ob_get_clean();
            $instance->setValue(null, $shopLogger);
            $finally();
        }

        return $logged;
    }

    /**
     * @return array{?string, ?string}
     */
    private function announcedFor(Cart $cart): array
    {
        $options = $this->getService(DeliveryOptionsProvider::class)->getOptions(
            $this->carrier,
            null,
            $cart,
            $this->factory->country(),
            null,
        );

        self::assertCount(1, $options);
        self::assertInstanceOf(DeliveryModuleOption::class, $options[0]);

        return [$options[0]->getMinimumDeliveryDate() ?: null, $options[0]->getMaximumDeliveryDate() ?: null];
    }

    private function day(int $daysFromToday): string
    {
        return (new \DateTimeImmutable('today'))->modify(\sprintf('+%d days', $daysFromToday))->format('Y-m-d');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function product(array $overrides = []): Product
    {
        $delay = $overrides['availabilityDelayDays'] ?? null;
        unset($overrides['availabilityDelayDays']);

        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100] + $overrides,
        );

        if (null !== $delay) {
            $product->setAvailabilityDelayDays($delay)->save($this->getPropelConnection());
        }

        return $product;
    }

    /**
     * @return array{0: Cart, 1: Customer}
     */
    private function cartReadyToPay(): array
    {
        $country = $this->factory->country();
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No Cheque module installed — run bin/test-prepare.');

        $this->serveCountryWithCarrier($this->carrier, $country, $this->getPropelConnection());
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        });
        $this->listen(TheliaEvents::MODULE_PAY, static function (OrderPaymentEvent $event): void {
            $event->stopPropagation();
        }, 1024);

        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->factory->address($customer, $country);

        $cart = $this->factory->cart($customer);
        $this->factory->cartItem($cart, $this->product());

        $cart
            ->setAddressDeliveryId($this->factory->cartAddress(null, $country)->getId())
            ->setAddressInvoiceId($this->factory->cartAddress(null, $country)->getId())
            ->setDeliveryModuleId($this->carrier->getId())
            ->setPaymentModuleId($paymentModule->getId())
            ->save($this->getPropelConnection());

        return [$cart, $customer];
    }

    private function place(Cart $cart, Customer $customer): int
    {
        $result = $this->getService(CheckoutPlacementService::class)->place(new CheckoutPlacementRequest(
            $cart,
            $customer,
            $this->factory->currency(),
            $this->factory->lang(),
        ));

        return (int) $result->orderId;
    }

    private function settings(): DeliveryDateSettings
    {
        return $this->getService(DeliveryDateSettings::class);
    }

    private function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }

    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByActive(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setMandatory(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }
}
