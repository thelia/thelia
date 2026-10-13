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
use Thelia\Api\Bridge\Propel\Event\DeliveryModuleOptionEvent;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\Service\CheckoutPlacementService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Domain\Shipping\DeliveryDate\Exception\InvalidDeliveryDateSettingsException;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateSettings;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryTimeEstimator;
use Thelia\Domain\Shipping\Service\DeliveryOptionsProvider;
use Thelia\Domain\Shipping\ShippingFacade;
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

    public function testTheCutoffMustBeAnHour(): void
    {
        $this->expectException(InvalidDeliveryDateSettingsException::class);

        $this->settings()->saveShopCutoffTime('2pm');
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
