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
use Thelia\Domain\Shipping\DeliveryDate\DTO\DeliveryEstimate;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\DeliveryClosureQuery;
use Thelia\Model\DeliveryDateRule;
use Thelia\Model\DeliveryDateRuleQuery;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Module;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;

/**
 * When a parcel sent by a carrier is expected, from the delays the merchant set.
 *
 * A carrier announces an estimate once both its transit days are set; its preparation days
 * default to none. The calendar is the one the delivery dates use: the closed days of the
 * week of the shop and of the carrier, and the exceptional closures. Like that calendar, it
 * reads neither the session nor the request, so a command that writes an e-mail gets the
 * same answer as the delivery step, and days are counted in the time zone PHP runs in.
 */
final readonly class DeliveryTimeEstimator
{
    /** The local hour, H:i, from which an order is prepared the next working day; empty for none. */
    public const string CUTOFF_CONFIG_NAME = 'delivery_cutoff_time';

    public const string CUTOFF_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public function __construct(
        private DeliveryIntervalCalculator $calculator,
    ) {
    }

    /**
     * @param int $extraPreparationDays working days the goods need on top of the carrier preparation
     */
    public function estimateFor(Module $module, int $extraPreparationDays = 0, ?\DateTimeImmutable $now = null): ?DeliveryEstimate
    {
        $rule = DeliveryDateRuleQuery::create()->findOneByModuleId($module->getId());

        if (!self::announcesAnEstimate($rule)) {
            return null;
        }

        $now ??= new \DateTimeImmutable('now');
        $shopClosedWeekdays = ClosedWeekdays::parse((string) ConfigQuery::read(ClosedWeekdays::SHOP_CONFIG_NAME, ''));
        $carrierClosedWeekdays = null === $rule->getClosedWeekdays() ? $shopClosedWeekdays : ClosedWeekdays::parse($rule->getClosedWeekdays());
        [$shopClosedDays, $carrierClosedDays] = $this->closedDaysFrom((int) $module->getId(), $now);

        return $this->calculator->interval(
            $now,
            max(0, (int) $rule->getPreparationDays()) + max(0, $extraPreparationDays),
            (int) $rule->getTransitMinDays(),
            (int) $rule->getTransitMaxDays(),
            $shopClosedWeekdays,
            $shopClosedDays,
            $carrierClosedWeekdays,
            $carrierClosedDays,
            self::cutoff(),
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

        if ([] === $productIds) {
            return 0;
        }

        $slowest = ProductQuery::create()
            ->filterById(array_unique($productIds), Criteria::IN)
            ->withColumn('MAX('.ProductTableMap::COL_AVAILABILITY_DELAY_DAYS.')', 'slowest')
            ->select(['slowest'])
            ->findOne();

        return max(0, (int) $slowest);
    }

    public static function announcesAnEstimate(?DeliveryDateRule $rule): bool
    {
        return null !== $rule && null !== $rule->getTransitMinDays() && null !== $rule->getTransitMaxDays();
    }

    /**
     * The cutoff hour of the shop, or null when it has none or holds something else than an hour.
     */
    public static function cutoff(): ?string
    {
        $stored = trim((string) ConfigQuery::read(self::CUTOFF_CONFIG_NAME, ''));

        return 1 === preg_match(self::CUTOFF_PATTERN, $stored) ? $stored : null;
    }

    /**
     * The closures from today on: those of the whole shop stop the preparation and the
     * delivery, those of the carrier only its deliveries.
     *
     * @return array{array<string, true>, array<string, true>}
     */
    private function closedDaysFrom(int $moduleId, \DateTimeImmutable $now): array
    {
        $first = $now->setTime(0, 0);
        $last = $first->modify(\sprintf('+%d days', 2 * DeliveryIntervalCalculator::SEARCH_LIMIT_DAYS));

        $closures = DeliveryClosureQuery::create()
            ->filterByModuleId($moduleId)
            ->_or()
            ->filterByModuleId(null, Criteria::ISNULL)
            ->filterByStartDate($last->format('Y-m-d'), Criteria::LESS_EQUAL)
            ->filterByEndDate($first->format('Y-m-d'), Criteria::GREATER_EQUAL)
            ->find();

        $shop = [];
        $carrier = [];

        foreach ($closures as $closure) {
            $start = DeliveryDateGuard::parseDay((string) $closure->getStartDate('Y-m-d'));
            $end = DeliveryDateGuard::parseDay((string) $closure->getEndDate('Y-m-d'));

            if (null === $start || null === $end) {
                continue;
            }

            for ($day = max($first, $start), $to = min($last, $end); $day <= $to; $day = $day->modify('+1 day')) {
                $key = $day->format('Y-m-d');
                $carrier[$key] = true;

                if (null === $closure->getModuleId()) {
                    $shop[$key] = true;
                }
            }
        }

        return [$shop, $carrier];
    }
}
