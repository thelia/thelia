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

namespace Thelia\Domain\Shipping\DeliveryDate\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Domain\Shipping\DeliveryDate\DTO\DeliveryDateOffer;
use Thelia\Domain\Shipping\DeliveryDate\DTO\DeliveryDay;
use Thelia\Domain\Shipping\DeliveryDate\DTO\DeliverySlotOffer;
use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Model\ConfigQuery;
use Thelia\Model\DeliveryClosureQuery;
use Thelia\Model\DeliveryDateRule;
use Thelia\Model\DeliveryDateRuleQuery;
use Thelia\Model\DeliverySlotBookingQuery;
use Thelia\Model\DeliverySlotI18nQuery;
use Thelia\Model\DeliverySlotQuery;
use Thelia\Model\Module;
use Thelia\Module\DeliveryDateAwareInterface;

/**
 * The days, and the slots of each day, a carrier offers.
 *
 * Everything it needs is handed over: the carrier, the day the window is counted from, the
 * language of the slot titles. It reads neither the session nor the cart, so the tunnel, the
 * front API and the guard that refuses an order all ask the same question and get the same
 * answer. Days are counted in the time zone PHP runs in, which is the shop's: a day is a
 * calendar day there, never an instant.
 *
 * One window costs the same few queries whatever its length — the rule, the closures, the
 * slots and their titles, the bookings of the window — and never one per day.
 */
final readonly class DeliveryDateCalendar
{
    /**
     * A bound on the window, whatever the rule says: a calendar of several years is a typing
     * mistake, and it would be drawn day by day.
     */
    public const int MAXIMUM_HORIZON_DAYS = 366;

    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * The shapes the module says it can honour, empty for a module that does not take dates.
     *
     * @return list<DeliveryDateChoiceMode>
     */
    public function acceptedChoiceModesOf(Module $module): array
    {
        try {
            $instance = $module->getModuleInstance($this->container);
        } catch (\Throwable) {
            // A module whose class is gone offers nothing; the delivery step deals with it.
            return [];
        }

        if (!$instance instanceof DeliveryDateAwareInterface) {
            return [];
        }

        return array_values(array_filter(
            $instance->getAcceptedDeliveryDateChoiceModes(),
            static fn (DeliveryDateChoiceMode $mode): bool => DeliveryDateChoiceMode::None !== $mode,
        ));
    }

    /**
     * What the buyer picks for this carrier: what the merchant set, as long as the module
     * accepts it, and None otherwise.
     */
    public function choiceModeOf(Module $module): DeliveryDateChoiceMode
    {
        return $this->resolveChoiceMode($module, DeliveryDateRuleQuery::create()->findOneByModuleId($module->getId()));
    }

    /**
     * The window of the carrier counted from today, or null when it offers no date.
     *
     * @param \DateTimeImmutable|null $now    the moment the window is drawn at: its day is today, and a slot of
     *                                        today whose hours are over is no longer available
     * @param string|null             $locale the language of the slot titles; a slot with no title in it is shown by its hours
     */
    public function offerFor(Module $module, ?\DateTimeImmutable $now = null, ?string $locale = null): ?DeliveryDateOffer
    {
        $rule = DeliveryDateRuleQuery::create()->findOneByModuleId($module->getId());
        $choiceMode = $this->resolveChoiceMode($module, $rule);

        if (null === $rule || DeliveryDateChoiceMode::None === $choiceMode) {
            return null;
        }

        $now ??= new \DateTimeImmutable('now');
        $today = $now->setTime(0, 0);
        $todayKey = $today->format('Y-m-d');
        $timeOfDay = $now->format('H:i');
        $first = $today->modify(\sprintf('+%d days', max(0, $rule->getMinimumDelayDays())));
        $last = $today->modify(\sprintf('+%d days', min(self::MAXIMUM_HORIZON_DAYS, max(0, $rule->getHorizonDays()))));

        if ($last < $first) {
            return new DeliveryDateOffer((int) $module->getId(), $choiceMode, []);
        }

        $closedWeekdays = ClosedWeekdays::parse(
            $rule->getClosedWeekdays() ?? (string) ConfigQuery::read(ClosedWeekdays::SHOP_CONFIG_NAME, ''),
        );
        $closedDays = $this->closedDaysBetween((int) $module->getId(), $first, $last);
        $slots = DeliveryDateChoiceMode::Slot === $choiceMode ? $this->slotsOf((int) $module->getId(), $locale) : [];
        $bookings = [] === $slots ? [] : $this->bookingsBetween(array_keys($slots), $first, $last);

        $days = [];

        for ($date = $first; $date <= $last; $date = $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            $open = !\in_array((int) $date->format('N'), $closedWeekdays, true) && !isset($closedDays[$key]);
            $daySlots = [];

            foreach ($slots as $slotId => $slot) {
                $taken = $bookings[$slotId][$key] ?? 0;
                // A slot of today whose hours are over can no longer be honoured.
                $over = $key === $todayKey && $slot['end'] <= $timeOfDay;

                $daySlots[] = new DeliverySlotOffer(
                    $slotId,
                    $slot['title'],
                    $slot['start'],
                    $slot['end'],
                    $open && !$over && (null === $slot['capacity'] || $taken < $slot['capacity']),
                );
            }

            $available = DeliveryDateChoiceMode::Slot === $choiceMode
                ? [] !== array_filter($daySlots, static fn (DeliverySlotOffer $slot): bool => $slot->available)
                : $open;

            $days[] = new DeliveryDay($date, $open, $available, $open ? $daySlots : []);
        }

        return new DeliveryDateOffer((int) $module->getId(), $choiceMode, $days);
    }

    private function resolveChoiceMode(Module $module, ?DeliveryDateRule $rule): DeliveryDateChoiceMode
    {
        $stored = DeliveryDateChoiceMode::tryFrom((string) $rule?->getChoiceMode()) ?? DeliveryDateChoiceMode::None;

        if (DeliveryDateChoiceMode::None === $stored) {
            return DeliveryDateChoiceMode::None;
        }

        // The rule outlives a module update that withdraws a shape, or a module that stops
        // implementing the contract: the merchant's setting is then ignored, not honoured
        // against what the carrier says it can do.
        return \in_array($stored, $this->acceptedChoiceModesOf($module), true) ? $stored : DeliveryDateChoiceMode::None;
    }

    /**
     * The days the shop or this carrier is closed between the two bounds, keyed by date.
     *
     * @return array<string, true>
     */
    private function closedDaysBetween(int $moduleId, \DateTimeImmutable $first, \DateTimeImmutable $last): array
    {
        $closures = DeliveryClosureQuery::create()
            ->filterByModuleId($moduleId)
            ->_or()
            ->filterByModuleId(null, Criteria::ISNULL)
            ->filterByStartDate($last->format('Y-m-d'), Criteria::LESS_EQUAL)
            ->filterByEndDate($first->format('Y-m-d'), Criteria::GREATER_EQUAL)
            ->find();

        $closed = [];

        foreach ($closures as $closure) {
            $start = DeliveryDateGuard::parseDay((string) $closure->getStartDate('Y-m-d'));
            $end = DeliveryDateGuard::parseDay((string) $closure->getEndDate('Y-m-d'));

            if (null === $start || null === $end) {
                continue;
            }

            $from = max($first, $start);
            $to = min($last, $end);

            for ($date = $from; $date <= $to; $date = $date->modify('+1 day')) {
                $closed[$date->format('Y-m-d')] = true;
            }
        }

        return $closed;
    }

    /**
     * @return array<int, array{title: ?string, start: string, end: string, capacity: ?int}>
     */
    private function slotsOf(int $moduleId, ?string $locale): array
    {
        $slots = [];

        foreach (DeliverySlotQuery::create()->filterByModuleId($moduleId)->orderByPosition()->orderByStartTime()->find() as $slot) {
            $slots[(int) $slot->getId()] = [
                'title' => null,
                'start' => (string) $slot->getStartTime('H:i'),
                'end' => (string) $slot->getEndTime('H:i'),
                'capacity' => null === $slot->getCapacity() ? null : (int) $slot->getCapacity(),
            ];
        }

        if (null !== $locale && [] !== $slots) {
            // Read off the i18n table rather than through getTranslation(), which would move the
            // shared slot instances to this language for whoever reads them next.
            $titles = DeliverySlotI18nQuery::create()
                ->filterById(array_keys($slots), Criteria::IN)
                ->filterByLocale($locale)
                ->find();

            foreach ($titles as $title) {
                $text = trim((string) $title->getTitle());
                $slots[(int) $title->getId()]['title'] = '' === $text ? null : $text;
            }
        }

        return $slots;
    }

    /**
     * How many orders hold each slot on each day of the window.
     *
     * @param list<int> $slotIds
     *
     * @return array<int, array<string, int>>
     */
    private function bookingsBetween(array $slotIds, \DateTimeImmutable $first, \DateTimeImmutable $last): array
    {
        $bookings = DeliverySlotBookingQuery::create()
            ->filterByDeliverySlotId($slotIds, Criteria::IN)
            ->filterByDeliveryDate(['min' => $first->format('Y-m-d'), 'max' => $last->format('Y-m-d')])
            ->find();

        $taken = [];

        foreach ($bookings as $booking) {
            $taken[(int) $booking->getDeliverySlotId()][(string) $booking->getDeliveryDate('Y-m-d')] = (int) $booking->getBooked();
        }

        return $taken;
    }
}
