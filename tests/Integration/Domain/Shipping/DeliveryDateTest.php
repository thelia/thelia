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
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\DTO\CheckoutViolation;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Exception\CheckoutRefusedException;
use Thelia\Domain\Checkout\Exception\DeliveryDateRequiredException;
use Thelia\Domain\Checkout\Exception\DeliveryDateUnavailableException;
use Thelia\Domain\Checkout\Exception\DeliverySlotFullException;
use Thelia\Domain\Checkout\Service\CheckoutPlacementService;
use Thelia\Domain\Checkout\Service\CheckoutValidationService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Shipping\DeliveryDate\DTO\DeliveryDateOffer;
use Thelia\Domain\Shipping\DeliveryDate\DTO\DeliveryDay;
use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Domain\Shipping\DeliveryDate\Exception\InvalidDeliveryDateSettingsException;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateCalendar;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateSettings;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliverySlotBooker;
use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Customer;
use Thelia\Model\DeliverySlot;
use Thelia\Model\DeliverySlotBookingQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Delivery\DeliveryDateTestCarrier;
use Thelia\Tests\Support\Delivery\RegistersDeliveryDateTestCarrier;

/**
 * The delivery day a buyer picks: the days a carrier offers, the guard that refuses a day it
 * does not, the place a slot gives away, and what the order keeps of it.
 */
final class DeliveryDateTest extends IntegrationTestCase
{
    use RegistersDeliveryDateTestCarrier;

    private FixtureFactory $factory;

    private Module $carrier;

    private string $shopClosedWeekdaysBefore;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->carrier = $this->registerDeliveryDateTestCarrier(static::getContainer(), $this->getPropelConnection());
        $this->shopClosedWeekdaysBefore = (string) ConfigQuery::read('delivery_closed_weekdays', '');
        ConfigQuery::write('delivery_closed_weekdays', '');
        $this->turnMandatoryConsentsOff();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        ConfigQuery::write('delivery_closed_weekdays', $this->shopClosedWeekdaysBefore);
        DeliveryDateTestCarrier::$acceptedModes = [DeliveryDateChoiceMode::Date, DeliveryDateChoiceMode::Slot];
        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    /**
     * Recette 2: a delay of two days and a horizon of three weeks.
     */
    public function testTheWindowOpensAfterTheDelayAndClosesAtTheHorizon(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 2, 21, null);

        $offer = $this->offer();

        self::assertSame(DeliveryDateChoiceMode::Date, $offer->choiceMode);
        self::assertSame($this->day(2), $offer->days[0]->date->format('Y-m-d'), 'The first day offered is two days from today.');
        self::assertSame($this->day(21), $offer->days[array_key_last($offer->days)]->date->format('Y-m-d'), 'The last day offered is three weeks from today.');
        self::assertCount(20, $offer->days, 'Every day in between is listed, so a calendar is drawn from the list alone.');
        self::assertNull($offer->day(new \DateTimeImmutable($this->day(1))), 'Tomorrow is before the delay.');
    }

    /**
     * Recette 2: Sundays are closed and listed as such.
     */
    public function testAClosedDayOfTheWeekIsListedButNotOffered(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 0, 14, [7]);

        foreach ($this->offer()->days as $day) {
            $isSunday = '7' === $day->date->format('N');

            self::assertSame(!$isSunday, $day->open, $day->date->format('l Y-m-d'));
            self::assertSame(!$isSunday, $day->available, $day->date->format('l Y-m-d'));
        }
    }

    /**
     * Ticket recommendation: a calendar of the shop, which a carrier may replace with its own.
     */
    public function testACarrierFollowsTheShopClosedDaysUnlessItSetsItsOwn(): void
    {
        $this->settings()->saveShopClosedWeekdays([7]);

        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 0, 14, null);
        self::assertFalse($this->firstDayOfTheWeek(7)->open, 'A carrier with no days of its own follows the shop.');

        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 0, 14, []);
        self::assertTrue($this->firstDayOfTheWeek(7)->open, 'A carrier that sets its own days, even none, no longer follows the shop.');
    }

    /**
     * Recette 6: an exceptional closure removes its days, whether it closes the shop or the carrier.
     */
    public function testAnExceptionalClosureOfTheShopOrOfTheCarrierRemovesItsDays(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 0, 20, []);
        $this->settings()->addClosure(null, $this->day(3), $this->day(4), 'Inventory');
        $this->settings()->addClosure($this->carrier, $this->day(10), $this->day(10));
        $this->settings()->addClosure($this->otherModule(), $this->day(12), $this->day(12));

        $offer = $this->offer();

        foreach ([3, 4, 10] as $closed) {
            self::assertFalse($offer->day(new \DateTimeImmutable($this->day($closed)))?->open, \sprintf('Day +%d is closed.', $closed));
        }

        foreach ([2, 5, 9, 11, 12] as $open) {
            self::assertTrue($offer->day(new \DateTimeImmutable($this->day($open)))?->open, \sprintf('Day +%d stays open: a closure of another carrier does not apply.', $open));
        }
    }

    /**
     * Recette 1 and the contract: a module that does not take dates offers none, whatever
     * the rule left in the database says.
     */
    public function testACarrierThatDoesNotTakeASetShapeOffersNoDate(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 0, 14, []);
        $this->settings()->addSlot($this->carrier, '09:00', '11:00', null);

        DeliveryDateTestCarrier::$acceptedModes = [DeliveryDateChoiceMode::Date];

        self::assertNull($this->calendar()->offerFor($this->carrier), 'A shape the module withdrew is ignored.');
        self::assertSame(DeliveryDateChoiceMode::None, $this->calendar()->choiceModeOf($this->carrier));

        $this->expectException(InvalidDeliveryDateSettingsException::class);
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 0, 14, []);
    }

    public function testANewShopOffersNoDateOnAnyCarrier(): void
    {
        foreach (ModuleQuery::create()->filterByType(2)->find() as $module) {
            if ($module->getId() === $this->carrier->getId()) {
                continue;
            }

            self::assertNull($this->calendar()->offerFor($module), $module->getCode().' offers no date out of the box.');
        }

        self::assertNull($this->calendar()->offerFor($this->carrier), 'Not even a carrier that takes dates, until the merchant sets a rule.');
    }

    /**
     * Recette 4: a slot of two orders is no longer offered once two orders hold it.
     */
    public function testAFullSlotIsNoLongerOfferedAndSaysNothingOfItsFilling(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 1, 10, []);
        $morning = $this->settings()->addSlot($this->carrier, '09:00', '11:00', 2, ['en_US' => 'Morning']);
        $noon = $this->settings()->addSlot($this->carrier, '11:00', '13:00', 2);

        $booker = $this->getService(DeliverySlotBooker::class);
        $booker->book((int) $morning->getId(), $this->day(1), $this->getPropelConnection());
        self::assertTrue($this->offer()->days[0]->slot((int) $morning->getId())?->available, 'One place left.');

        $booker->book((int) $morning->getId(), $this->day(1), $this->getPropelConnection());

        $day = $this->offer()->days[0];
        self::assertFalse($day->slot((int) $morning->getId())?->available, 'Two orders fill a slot of two.');
        self::assertTrue($day->slot((int) $noon->getId())?->available);
        self::assertTrue($day->available, 'The day stays available while one of its slots is.');
        self::assertTrue($this->offer()->days[1]->slot((int) $morning->getId())?->available, 'A slot fills day by day.');
        self::assertSame('Morning', $day->slot((int) $morning->getId())?->title);

        $booker->book((int) $noon->getId(), $this->day(1), $this->getPropelConnection());
        $booker->book((int) $noon->getId(), $this->day(1), $this->getPropelConnection());
        self::assertFalse($this->offer()->days[0]->available, 'A day whose slots are all full is not available.');
        self::assertTrue($this->offer()->days[0]->open, 'It is still a day the carrier delivers on.');

        $this->expectException(DeliverySlotFullException::class);
        $booker->book((int) $morning->getId(), $this->day(1), $this->getPropelConnection());
    }

    /**
     * A delay of zero days offers today, but a slot of today whose hours are over can no
     * longer be honoured: a buyer at 15:00 is not offered the morning round.
     */
    public function testASlotOfTodayWhoseHoursAreOverIsNotOffered(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 0, 3, []);
        $morning = $this->settings()->addSlot($this->carrier, '09:00', '11:00', null);
        $evening = $this->settings()->addSlot($this->carrier, '18:00', '20:00', null);

        $offer = $this->calendar()->offerFor($this->carrier, new \DateTimeImmutable('today 15:00'))
            ?? throw new \LogicException('The carrier offers no date.');
        $today = $offer->days[0];

        self::assertSame($this->day(0), $today->date->format('Y-m-d'));
        self::assertFalse($today->slot((int) $morning->getId())?->available, 'The morning of today is over.');
        self::assertTrue($today->slot((int) $evening->getId())?->available, 'The evening of today is still ahead.');
        self::assertTrue($today->available);
        self::assertTrue($offer->days[1]->slot((int) $morning->getId())?->available, 'Tomorrow morning is still ahead.');

        $late = $this->calendar()->offerFor($this->carrier, new \DateTimeImmutable('today 20:00'))
            ?? throw new \LogicException('The carrier offers no date.');

        self::assertFalse($late->days[0]->available, 'Today has no slot left that can still be honoured.');
        self::assertTrue($late->days[0]->open, 'It is still a day the carrier delivers on.');
    }

    /**
     * Dev check: a date forged in a request, outside the window or on a closed day, is refused.
     */
    public function testAForgedDayIsRefusedWhateverThePageShowed(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 2, 21, [7]);
        [$cart] = $this->cartReadyToPay();

        $refused = [
            'before the delay' => [$this->day(1), null],
            'after the horizon' => [$this->day(22), null],
            'on a closed day' => [$this->firstDayOfTheWeek(7)->date->format('Y-m-d'), null],
            'not a real day' => ['2026-02-30', null],
            'not a day at all' => ['next tuesday', null],
            'a slot sent to a carrier of whole days' => [$this->firstOpenDay(), 1],
        ];

        foreach ($refused as $case => [$date, $slotId]) {
            try {
                $this->cartFacade()->chooseDeliveryDate($cart, $date, $slotId);
                self::fail(\sprintf('A day %s must be refused.', $case));
            } catch (DeliveryDateUnavailableException) {
                self::assertNull($cart->getDeliveryDate(), \sprintf('Nothing is written for a day %s.', $case));
            }
        }

        $this->cartFacade()->chooseDeliveryDate($cart, $this->firstOpenDay());
        self::assertSame($this->firstOpenDay(), $cart->getDeliveryDate('Y-m-d'));
    }

    public function testASlotIsRequiredFromACarrierOfSlotsAndMustBeOneOfItsOwn(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 1, 10, []);
        $slot = $this->settings()->addSlot($this->carrier, '09:00', '11:00', null);
        $foreignSlot = $this->settings()->addSlot($this->otherModule(), '14:00', '16:00', null);
        [$cart] = $this->cartReadyToPay();

        try {
            $this->cartFacade()->chooseDeliveryDate($cart, $this->day(1));
            self::fail('A day without its slot must be refused.');
        } catch (DeliveryDateRequiredException $refusal) {
            self::assertSame(CheckoutViolationCode::DeliveryDateMissing->value, $refusal->violationCode());
        }

        try {
            $this->cartFacade()->chooseDeliveryDate($cart, $this->day(1), (int) $foreignSlot->getId());
            self::fail('A slot of another carrier must be refused.');
        } catch (DeliveryDateUnavailableException) {
        }

        $this->cartFacade()->chooseDeliveryDate($cart, $this->day(1), (int) $slot->getId());
        self::assertSame((int) $slot->getId(), (int) $cart->getDeliverySlotId());
    }

    /**
     * The carrier offers dates: an order without one is refused by the same guard the
     * tunnel, the placement API and the validation endpoint all go through.
     */
    public function testACartWithoutTheDayItsCarrierAsksForCannotBePlaced(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 0, 14, []);
        [$cart, $customer] = $this->cartReadyToPay();

        self::assertSame(
            [CheckoutViolationCode::DeliveryDateMissing->value],
            array_map(static fn (CheckoutViolation $violation): string => $violation->code, $this->getService(CheckoutValidationService::class)->collectViolations($cart)),
        );

        try {
            $this->place($cart, $customer);
            self::fail('The placement must be refused.');
        } catch (CheckoutRefusedException $refusal) {
            self::assertSame(CheckoutViolationCode::DeliveryDateMissing->value, $refusal->violations[0]->code);
        }
    }

    /**
     * Recette 1: no carrier offers dates, and the order is placed as it always was.
     */
    public function testWithoutDatesTheOrderIsPlacedAsItAlwaysWas(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();

        $order = OrderQuery::create()->findPk($this->place($cart, $customer));

        self::assertNotNull($order);
        self::assertNull($order->getDeliveryDate());
        self::assertNull($order->getDeliverySlotId());
    }

    /**
     * Recette 3 and 4: the day and the hours land on the order, and the place is taken.
     */
    public function testTheDayAndTheSlotAreCopiedOnTheOrderAndThePlaceIsTaken(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 1, 10, []);
        $slot = $this->settings()->addSlot($this->carrier, '09:00', '11:00', 2);
        [$cart, $customer] = $this->cartReadyToPay();
        $this->cartFacade()->chooseDeliveryDate($cart, $this->day(1), (int) $slot->getId());

        $order = OrderQuery::create()->findPk($this->place($cart, $customer));

        self::assertNotNull($order);
        self::assertSame($this->day(1), $order->getDeliveryDay());
        self::assertSame('09:00', $order->getDeliverySlotStartsAt());
        self::assertSame('11:00', $order->getDeliverySlotEndsAt());
        self::assertSame(1, $this->bookedIn($slot, $this->day(1)));
    }

    /**
     * Dev check: the order keeps its day and hours once the slot is deleted from the settings.
     */
    public function testAnOrderKeepsItsDayWhenTheSlotIsDeleted(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 1, 10, []);
        $slot = $this->settings()->addSlot($this->carrier, '14:00', '16:00', null);
        [$cart, $customer] = $this->cartReadyToPay();
        $this->cartFacade()->chooseDeliveryDate($cart, $this->day(1), (int) $slot->getId());
        $orderId = $this->place($cart, $customer);

        $this->settings()->deleteSlot($slot);

        $order = OrderQuery::create()->findPk($orderId);
        self::assertNotNull($order);
        self::assertNull($order->getDeliverySlotId(), 'The order no longer points at a slot that is gone.');
        self::assertSame($this->day(1), $order->getDeliveryDay());
        self::assertSame('14:00', $order->getDeliverySlotStartsAt());
        self::assertSame('16:00', $order->getDeliverySlotEndsAt());
    }

    /**
     * Dev check: the guard read a place left, another order took it before this one reached
     * the conditional update. Refused explicitly, and no order is written.
     */
    public function testTheLastPlaceTakenDuringThePlacementRefusesTheOrder(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 1, 10, []);
        $slot = $this->settings()->addSlot($this->carrier, '09:00', '11:00', 1);
        [$cart, $customer] = $this->cartReadyToPay();
        $this->cartFacade()->chooseDeliveryDate($cart, $this->day(1), (int) $slot->getId());

        // After the guard, before the order transaction: another buyer takes the place.
        $this->listen(TheliaEvents::ORDER_PAY, function () use ($slot): void {
            $this->getService(DeliverySlotBooker::class)->book((int) $slot->getId(), $this->day(1), $this->getPropelConnection());
        }, 1024);

        try {
            $this->place($cart, $customer);
            self::fail('The second buyer of the last place must be refused.');
        } catch (CheckoutRefusedException $refusal) {
            self::assertSame(CheckoutViolationCode::DeliverySlotFull->value, $refusal->violations[0]->code);
            self::assertSame('delivery', $refusal->violations[0]->stepCode);
        }

        // That the order row is rolled back with the refusal is not observable here: the test
        // transaction turns the facade's inner rollback into a no-op (see
        // OrderFacadeRefAllocationTest). The booking is what the refusal is about.
        self::assertSame(1, $this->bookedIn($slot, $this->day(1)), 'Only the other buyer holds the place.');
    }

    /**
     * A payment that failed brings the buyer back to the same cart: the place their own
     * unpaid order holds is theirs, and the slot must not read as full to them.
     */
    public function testTheBuyerComingBackToPayIsNotRefusedTheirOwnPlace(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 1, 10, []);
        $slot = $this->settings()->addSlot($this->carrier, '09:00', '11:00', 1);
        [$cart, $customer] = $this->cartReadyToPay();
        $this->cartFacade()->chooseDeliveryDate($cart, $this->day(1), (int) $slot->getId());
        $this->place($cart, $customer);
        self::assertSame(1, $this->bookedIn($slot, $this->day(1)));

        self::assertSame([], array_map(static fn (CheckoutViolation $violation): string => $violation->code, $this->getService(CheckoutValidationService::class)->collectViolations($cart)));
        self::assertFalse($this->cartFacade()->dropDeliveryDateIfNoLongerPossible($cart), 'The day stays on the cart.');
    }

    /**
     * Recette 5: the day falls with the carrier it was picked for.
     */
    public function testChangingTheCarrierDropsTheDay(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 0, 14, []);
        [$cart] = $this->cartReadyToPay();
        $this->cartFacade()->chooseDeliveryDate($cart, $this->firstOpenDay());

        $cart->setDeliveryModuleId($this->carrier->getId())->save();
        self::assertSame($this->firstOpenDay(), $cart->getDeliveryDate('Y-m-d'), 'The same carrier written again keeps the day.');

        $cart->setDeliveryModuleId($this->otherModule()->getId())->save();
        self::assertNull($cart->getDeliveryDate());
        self::assertNull($cart->getDeliverySlotId());
    }

    public function testACarrierAndADayWrittenTogetherAreBothKept(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 0, 14, []);
        [$cart] = $this->cartReadyToPay();
        $cart->setDeliveryModuleId($this->otherModule()->getId())->save();

        $cart->setDeliveryModuleId($this->carrier->getId())->setDeliveryDate($this->firstOpenDay())->save();

        self::assertSame($this->firstOpenDay(), $cart->getDeliveryDate('Y-m-d'), 'The day written with its carrier is the choice, not a leftover.');
    }

    public function testADayThatBecameImpossibleIsDroppedAndSaidSo(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 0, 14, []);
        [$cart] = $this->cartReadyToPay();
        $this->cartFacade()->chooseDeliveryDate($cart, $this->day(3));

        self::assertFalse($this->cartFacade()->dropDeliveryDateIfNoLongerPossible($cart), 'A day still offered is kept.');

        $this->settings()->addClosure(null, $this->day(3), $this->day(3));

        self::assertTrue($this->cartFacade()->dropDeliveryDateIfNoLongerPossible($cart));
        self::assertNull($cart->getDeliveryDate());
    }

    /**
     * A cancelled order gives its place back; brought back to life, it takes it again.
     */
    public function testCancellingTheOrderGivesThePlaceBack(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 1, 10, []);
        $slot = $this->settings()->addSlot($this->carrier, '09:00', '11:00', 1);
        [$cart, $customer] = $this->cartReadyToPay();
        $this->cartFacade()->chooseDeliveryDate($cart, $this->day(1), (int) $slot->getId());
        $order = OrderQuery::create()->findPk($this->place($cart, $customer));
        self::assertNotNull($order);
        self::assertSame(1, $this->bookedIn($slot, $this->day(1)));

        $order->setCancelled($this->dispatcher());
        self::assertSame(0, $this->bookedIn($slot, $this->day(1)), 'The place is free again.');

        $event = (new OrderEvent($order))->setStatus((int) OrderStatusQuery::create()->findOneByCode('paid')?->getId())->forceStatusTransition();
        $this->dispatcher()->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
        self::assertSame(1, $this->bookedIn($slot, $this->day(1)), 'The order holds its place again.');
    }

    /**
     * What the tunnel and the front API list as carriers says which ones offer a day.
     */
    public function testTheCarrierListSaysWhichCarrierOffersADay(): void
    {
        [$cart] = $this->cartReadyToPay();
        $country = \Thelia\Model\CartAddressQuery::create()->findPk($cart->getAddressDeliveryId())?->getCountry();

        $choiceOf = function () use ($cart, $country): ?string {
            foreach ($this->getService(ShippingFacade::class)->listValidMethodsAsResourceApi($cart, $country) as $carrier) {
                if ($carrier->getId() === $this->carrier->getId()) {
                    return $carrier->getDeliveryDateChoice();
                }
            }

            return null;
        };

        self::assertSame('none', $choiceOf());

        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 0, 7, []);
        self::assertSame('slot', $choiceOf());
    }

    /**
     * A module pricing by the day reads it off the postage event.
     */
    public function testThePostageEventCarriesTheDayPicked(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 0, 14, []);
        [$cart] = $this->cartReadyToPay();

        self::assertNull((new DeliveryPostageEvent($this->carrier, $cart))->getDeliveryDate());

        $this->cartFacade()->chooseDeliveryDate($cart, $this->firstOpenDay());
        self::assertSame($this->firstOpenDay(), (new DeliveryPostageEvent($this->carrier, $cart))->getDeliveryDate()?->format('Y-m-d'));
    }

    /**
     * The day belongs to the carrier it was picked for: another carrier quoted for the same
     * cart, by its model or by its instance, is quoted without it.
     */
    public function testAnotherCarrierQuotedForTheCartIsNotHandedTheDayPicked(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 0, 14, []);
        [$cart] = $this->cartReadyToPay();
        $this->cartFacade()->chooseDeliveryDate($cart, $this->firstOpenDay());
        $otherCarrier = $this->otherModule();

        self::assertSame(
            $this->firstOpenDay(),
            (new DeliveryPostageEvent($this->carrier->getModuleInstance(static::getContainer()), $cart))->getDeliveryDate()?->format('Y-m-d'),
            'The carrier of the cart, quoted by its instance, reads the day picked for it.',
        );
        self::assertNull((new DeliveryPostageEvent($otherCarrier, $cart))->getDeliveryDate());
        self::assertNull(
            (new DeliveryPostageEvent($otherCarrier->getModuleInstance(static::getContainer()), $cart))->getDeliveryDate(),
            'The carriers of the delivery loop are quoted by their instance.',
        );
    }

    /**
     * The calendar is asked by the API, the tunnel and the guard alike: it needs no request.
     */
    public function testTheCalendarNeedsNoSessionNorRequest(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 0, 3, []);
        $requestStack = $this->getService(RequestStack::class);
        $requests = [];

        while (null !== $request = $requestStack->pop()) {
            $requests[] = $request;
        }

        try {
            self::assertCount(4, $this->offer()->days);
        } finally {
            foreach (array_reverse($requests) as $request) {
                $requestStack->push($request);
            }
        }
    }

    public function testSettingsTheMerchantCannotSaveAreRefused(): void
    {
        $refusals = [
            'a negative delay' => fn () => $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, -1, 5, null),
            'a horizon before the delay' => fn () => $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 5, 4, null),
            'a horizon of years' => fn () => $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 0, 2000, null),
            'a slot ending before it starts' => fn () => $this->settings()->addSlot($this->carrier, '11:00', '09:00', null),
            'a slot of no hour' => fn () => $this->settings()->addSlot($this->carrier, '9h', '11:00', null),
            'a capacity of zero' => fn () => $this->settings()->addSlot($this->carrier, '09:00', '11:00', 0),
            'a closure ending before it starts' => fn () => $this->settings()->addClosure(null, $this->day(5), $this->day(4)),
        ];

        foreach ($refusals as $case => $save) {
            try {
                $save();
                self::fail(\sprintf('%s must be refused.', ucfirst($case)));
            } catch (InvalidDeliveryDateSettingsException) {
            }
        }

        self::assertInstanceOf(DeliverySlot::class, $this->settings()->addSlot($this->carrier, '9:00', '11:00', null), 'An hour without its leading zero is an hour.');
    }

    private function offer(): DeliveryDateOffer
    {
        return $this->calendar()->offerFor($this->carrier, locale: 'en_US') ?? throw new \LogicException('The carrier offers no date.');
    }

    private function firstDayOfTheWeek(int $isoDay): DeliveryDay
    {
        foreach ($this->offer()->days as $day) {
            if ((int) $day->date->format('N') === $isoDay) {
                return $day;
            }
        }

        throw new \LogicException('The window holds no such day.');
    }

    private function firstOpenDay(): string
    {
        foreach ($this->offer()->days as $day) {
            if ($day->available) {
                return $day->date->format('Y-m-d');
            }
        }

        throw new \LogicException('The window holds no open day.');
    }

    private function day(int $daysFromToday): string
    {
        return (new \DateTimeImmutable('today'))->modify(\sprintf('+%d days', $daysFromToday))->format('Y-m-d');
    }

    private function bookedIn(DeliverySlot $slot, string $date): int
    {
        return (int) DeliverySlotBookingQuery::create()
            ->filterByDeliverySlotId($slot->getId())
            ->filterByDeliveryDate($date)
            ->findOne()?->getBooked();
    }

    private function otherModule(): Module
    {
        return ModuleQuery::create()->findOneByCode('VirtualProductDelivery')
            ?? throw new \RuntimeException('No VirtualProductDelivery module installed — run bin/test-prepare.');
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
        // The order is written by ORDER_PAY; the module page it would then answer with is not
        // what these tests are about.
        $this->listen(TheliaEvents::MODULE_PAY, static function (OrderPaymentEvent $event): void {
            $event->stopPropagation();
        }, 1024);

        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->factory->address($customer, $country);

        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );

        $cart = $this->factory->cart($customer);
        $this->factory->cartItem($cart, $product);

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

    private function calendar(): DeliveryDateCalendar
    {
        return $this->getService(DeliveryDateCalendar::class);
    }

    private function cartFacade(): CartFacade
    {
        return $this->getService(CartFacade::class);
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
