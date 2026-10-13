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
use Thelia\Model\Cart;
use Thelia\Model\DeliveryDateRule;
use Thelia\Model\Module;
use Thelia\Model\Product;

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
    ) {
    }

    /**
     * @param int $extraPreparationDays working days the goods need on top of the carrier preparation
     */
    public function estimateFor(Module $module, int $extraPreparationDays = 0, ?\DateTimeImmutable $now = null): ?DeliveryEstimate
    {
        $moduleId = (int) $module->getId();
        $rule = $this->calendar->ruleOf($moduleId);

        if (!self::announcesAnEstimate($rule)) {
            return null;
        }

        $now ??= new \DateTimeImmutable('now');
        $shopClosedWeekdays = $this->calendar->shopClosedWeekdays();

        return $this->calculator->interval(
            $now,
            max(0, (int) $rule->getPreparationDays()) + max(0, $extraPreparationDays),
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
        return $this->estimateFor($module, $this->availabilityDelayOf($cart), $now);
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
     * @phpstan-assert-if-true !null $rule
     */
    public static function announcesAnEstimate(?DeliveryDateRule $rule): bool
    {
        return null !== $rule && null !== $rule->getTransitMinDays() && null !== $rule->getTransitMaxDays();
    }
}
