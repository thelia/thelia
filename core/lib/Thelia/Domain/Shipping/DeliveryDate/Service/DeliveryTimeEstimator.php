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

use Thelia\Domain\Shipping\DeliveryDate\DTO\DeliveryEstimate;
use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Log\ThrowableTrace;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\DeliveryDateRule;
use Thelia\Model\Module;
use Thelia\Model\Product;
use Thelia\Tools\TerminalText;

/**
 * When a parcel sent by a carrier is expected, from the delays the merchant set.
 *
 * A carrier announces an estimate once both its transit days are set; its preparation days
 * default to none. The calendar is the one the delivery dates use: the closed days of the
 * week of the shop and of the carrier, and the exceptional closures, read once per request
 * by DeliveryEstimateCalendar. It reads neither the session nor the request, so a command
 * that writes an e-mail gets the same answer as the delivery step, and days are counted in
 * the time zone PHP runs in.
 */
final readonly class DeliveryTimeEstimator
{
    /** The local hour, H:i, from which an order is prepared the next working day; empty for none. */
    public const string CUTOFF_CONFIG_NAME = 'delivery_cutoff_time';

    public const string CUTOFF_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public function __construct(
        private DeliveryIntervalCalculator $calculator,
        private DeliveryEstimateCalendar $calendar,
        private DeliveryDateCalendar $deliveryDateCalendar,
    ) {
    }

    /**
     * @param int $extraPreparationDays working days the goods need on top of the carrier preparation
     */
    public function estimateFor(Module $module, int $extraPreparationDays = 0, ?\DateTimeImmutable $now = null): ?DeliveryEstimate
    {
        return $this->guarded($module, fn (): ?DeliveryEstimate => $this->count($module, $extraPreparationDays, $now));
    }

    private function count(Module $module, int $extraPreparationDays, ?\DateTimeImmutable $now): ?DeliveryEstimate
    {
        $moduleId = (int) $module->getId();
        $rule = $this->calendar->ruleOf($moduleId);

        if (!self::announcesAnEstimate($rule) || $this->letsTheBuyerPickADay($module, $rule)) {
            return null;
        }

        $now ??= new \DateTimeImmutable('now');
        $shopClosedWeekdays = $this->calendar->shopClosedWeekdays();

        return $this->calculator->interval(
            $now,
            // Zero and one day of preparation both ship the day the order comes in: the extra
            // days of the goods count from there, whichever of the two the carrier says.
            max(1, self::boundedDays((int) $rule->getPreparationDays())) + self::boundedDays($extraPreparationDays),
            (int) $rule->getTransitMinDays(),
            (int) $rule->getTransitMaxDays(),
            $shopClosedWeekdays,
            $this->calendar->shopClosedDaysFrom($now),
            null === $rule->getClosedWeekdays() ? $shopClosedWeekdays : ClosedWeekdays::parse($rule->getClosedWeekdays()),
            $this->calendar->carrierClosedDaysFrom($moduleId, $now),
            $this->calendar->cutoff(),
        );
    }

    /**
     * The estimate of the carrier for this cart: the slowest product of the cart sets the
     * extra preparation.
     */
    public function estimateForCart(Module $module, Cart $cart, ?\DateTimeImmutable $now = null): ?DeliveryEstimate
    {
        return $this->guarded($module, fn (): ?DeliveryEstimate => $this->count($module, $this->availabilityDelayOf($cart), $now));
    }

    public function estimateForProduct(Module $module, Product $product, ?\DateTimeImmutable $now = null): ?DeliveryEstimate
    {
        return $this->estimateFor($module, max(0, (int) $product->getAvailabilityDelayDays()), $now);
    }

    /**
     * The availability delay of the slowest product of the cart, in working days.
     */
    public function availabilityDelayOf(Cart $cart): int
    {
        $productIds = [];

        foreach ($cart->getCartItems() as $item) {
            $productIds[] = (int) $item->getProductId();
        }

        return $this->calendar->slowestAvailabilityDelayOf($productIds);
    }

    /**
     * The estimate is information: a failure to count it, a database that does not answer
     * or a carrier whose settings cannot be read, leaves the page or the order without one,
     * it never takes them down.
     *
     * @param callable(): ?DeliveryEstimate $estimate
     */
    private function guarded(Module $module, callable $estimate): ?DeliveryEstimate
    {
        try {
            return $estimate();
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->addError(\sprintf('The delivery of carrier %s could not be estimated', TerminalText::singleLine((string) $module->getCode())), ThrowableTrace::describe($throwable));

            return null;
        }
    }

    /**
     * A carrier the buyer picks a day from shows that calendar, not an estimate: two dates
     * counted by two different rules would be two promises.
     */
    private function letsTheBuyerPickADay(Module $module, DeliveryDateRule $rule): bool
    {
        return DeliveryDateChoiceMode::None !== $this->deliveryDateCalendar->choiceModeUnder($module, $rule);
    }

    /**
     * A delay longer than the horizon of the calendar is a typing mistake: it would be walked
     * day by day.
     */
    private static function boundedDays(int $days): int
    {
        return max(0, min(DeliveryDateCalendar::MAXIMUM_HORIZON_DAYS, $days));
    }

    /**
     * @phpstan-assert-if-true !null $rule
     */
    public static function announcesAnEstimate(?DeliveryDateRule $rule): bool
    {
        return null !== $rule && null !== $rule->getTransitMinDays() && null !== $rule->getTransitMaxDays();
    }
}
