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
use Propel\Runtime\Exception\PropelException;
use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Domain\Shipping\DeliveryDate\Exception\InvalidDeliveryDateSettingsException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\DeliveryClosure;
use Thelia\Model\DeliveryClosureQuery;
use Thelia\Model\DeliveryDateRule;
use Thelia\Model\DeliveryDateRuleQuery;
use Thelia\Model\DeliverySlot;
use Thelia\Model\DeliverySlotQuery;
use Thelia\Model\Module;

/**
 * What the merchant sets about delivery dates: the closed days of the shop, and per carrier
 * the shape of the choice, the delay, the horizon, its own closed days, its slots and its
 * closures.
 *
 * Every rule a setting has to follow is checked here, so a back office, an import or a test
 * writing the same setting meets the same refusals. Orders already placed are never touched:
 * they keep the day and the hours they were placed with.
 */
final readonly class DeliveryDateSettings
{
    public function __construct(
        private DeliveryDateCalendar $calendar,
    ) {
    }

    /**
     * @return list<int> ISO days of the week, 1 (Monday) to 7 (Sunday)
     */
    public function shopClosedWeekdays(): array
    {
        return ClosedWeekdays::parse((string) ConfigQuery::read(ClosedWeekdays::SHOP_CONFIG_NAME, ''));
    }

    /**
     * @param iterable<int|string> $weekdays
     */
    public function saveShopClosedWeekdays(iterable $weekdays): void
    {
        ConfigQuery::write(ClosedWeekdays::SHOP_CONFIG_NAME, ClosedWeekdays::format($weekdays), false, true);
    }

    public function ruleOf(Module $module): ?DeliveryDateRule
    {
        return DeliveryDateRuleQuery::create()->findOneByModuleId($module->getId());
    }

    /**
     * @param iterable<int|string>|null $closedWeekdays the carrier's own closed days, null to follow the shop
     *
     * @throws InvalidDeliveryDateSettingsException
     * @throws PropelException
     */
    public function saveRule(
        Module $module,
        DeliveryDateChoiceMode $choiceMode,
        int $minimumDelayDays,
        int $horizonDays,
        ?iterable $closedWeekdays,
    ): DeliveryDateRule {
        if (DeliveryDateChoiceMode::None !== $choiceMode && !\in_array($choiceMode, $this->calendar->acceptedChoiceModesOf($module), true)) {
            throw new InvalidDeliveryDateSettingsException('This carrier does not offer this kind of delivery date.');
        }

        if ($minimumDelayDays < 0) {
            throw new InvalidDeliveryDateSettingsException('The minimum delay cannot be negative.');
        }

        if ($horizonDays < $minimumDelayDays) {
            throw new InvalidDeliveryDateSettingsException('The last day offered cannot come before the first one.');
        }

        if ($horizonDays > DeliveryDateCalendar::MAXIMUM_HORIZON_DAYS) {
            throw new InvalidDeliveryDateSettingsException('Delivery dates cannot be offered more than %max days ahead.', ['%max' => (string) DeliveryDateCalendar::MAXIMUM_HORIZON_DAYS]);
        }

        $rule = $this->ruleOf($module) ?? (new DeliveryDateRule())->setModuleId($module->getId());

        $rule
            ->setChoiceMode($choiceMode->value)
            ->setMinimumDelayDays($minimumDelayDays)
            ->setHorizonDays($horizonDays)
            ->setClosedWeekdays(null === $closedWeekdays ? null : ClosedWeekdays::format($closedWeekdays))
            ->save();

        return $rule;
    }

    /**
     * @return list<DeliverySlot>
     */
    public function slotsOf(Module $module): array
    {
        return array_values(iterator_to_array(
            DeliverySlotQuery::create()->filterByModuleId($module->getId())->orderByPosition()->orderByStartTime()->find(),
        ));
    }

    /**
     * @param array<string, string> $titles the slot name per locale, optional
     *
     * @throws InvalidDeliveryDateSettingsException
     * @throws PropelException
     */
    public function addSlot(Module $module, string $startTime, string $endTime, ?int $capacity, array $titles = []): DeliverySlot
    {
        $last = DeliverySlotQuery::create()->filterByModuleId($module->getId())->orderByPosition('desc')->findOne();

        $slot = (new DeliverySlot())
            ->setModuleId($module->getId())
            ->setPosition(null === $last ? 1 : $last->getPosition() + 1);

        return $this->writeSlot($slot, $startTime, $endTime, $capacity, $titles);
    }

    /**
     * Orders already placed keep the hours they were placed with: they are copied on them.
     *
     * @param array<string, string> $titles
     *
     * @throws InvalidDeliveryDateSettingsException
     * @throws PropelException
     */
    public function updateSlot(DeliverySlot $slot, string $startTime, string $endTime, ?int $capacity, array $titles = []): DeliverySlot
    {
        return $this->writeSlot($slot, $startTime, $endTime, $capacity, $titles);
    }

    /**
     * A cart holding the slot loses it and is asked again; an order keeps its day and hours.
     *
     * @throws PropelException
     */
    public function deleteSlot(DeliverySlot $slot): void
    {
        $slot->delete();
    }

    /**
     * @return list<DeliveryClosure> the closures of the carrier, or of the shop for a null module, the latest first
     */
    public function closuresOf(?Module $module): array
    {
        $query = DeliveryClosureQuery::create();
        $query = null === $module ? $query->filterByModuleId(null, Criteria::ISNULL) : $query->filterByModuleId($module->getId());

        return array_values(iterator_to_array($query->orderByStartDate('desc')->find()));
    }

    /**
     * @param Module|null $module the carrier closed, null for the whole shop
     *
     * @throws InvalidDeliveryDateSettingsException
     * @throws PropelException
     */
    public function addClosure(?Module $module, string $startDate, string $endDate, ?string $label = null): DeliveryClosure
    {
        $start = DeliveryDateGuard::parseDay($startDate);
        $end = DeliveryDateGuard::parseDay($endDate);

        if (null === $start || null === $end) {
            throw new InvalidDeliveryDateSettingsException('A closure needs a first and a last day.');
        }

        if ($end < $start) {
            throw new InvalidDeliveryDateSettingsException('The last day of a closure cannot come before its first day.');
        }

        $label = null === $label ? null : trim($label);

        $closure = (new DeliveryClosure())
            ->setModuleId($module?->getId())
            ->setStartDate($start->format('Y-m-d'))
            ->setEndDate($end->format('Y-m-d'))
            ->setLabel('' === $label ? null : $label);
        $closure->save();

        return $closure;
    }

    /**
     * @throws PropelException
     */
    public function deleteClosure(DeliveryClosure $closure): void
    {
        $closure->delete();
    }

    /**
     * @param array<string, string> $titles
     *
     * @throws InvalidDeliveryDateSettingsException
     * @throws PropelException
     */
    private function writeSlot(DeliverySlot $slot, string $startTime, string $endTime, ?int $capacity, array $titles): DeliverySlot
    {
        $start = self::parseTime($startTime);
        $end = self::parseTime($endTime);

        if (null === $start || null === $end) {
            throw new InvalidDeliveryDateSettingsException('A slot needs a start and an end hour, written HH:MM.');
        }

        if ($end <= $start) {
            throw new InvalidDeliveryDateSettingsException('A slot must end after it starts.');
        }

        if (null !== $capacity && $capacity < 1) {
            throw new InvalidDeliveryDateSettingsException('The capacity of a slot is at least one order, or left empty for no limit.');
        }

        $slot
            ->setStartTime($start.':00')
            ->setEndTime($end.':00')
            ->setCapacity($capacity);

        foreach ($titles as $locale => $title) {
            $title = trim($title);
            $slot->setLocale($locale)->setTitle('' === $title ? null : $title);
        }

        $slot->save();

        return $slot;
    }

    /**
     * An hour written H:i, 00:00 to 23:59, answered zero-padded so two hours compare as text.
     */
    private static function parseTime(string $time): ?string
    {
        if (1 !== preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim($time), $parts)) {
            return null;
        }

        return \sprintf('%02d:%s', (int) $parts[1], $parts[2]);
    }
}
