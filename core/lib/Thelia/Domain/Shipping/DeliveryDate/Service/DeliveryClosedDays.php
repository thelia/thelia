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
use Thelia\Model\ConfigQuery;
use Thelia\Model\DeliveryClosureQuery;
use Thelia\Model\DeliveryDateRule;
use Thelia\Model\DeliveryDateRuleQuery;

/**
 * The days a carrier does not deliver on: the days of the week the shop, or the carrier's own
 * rule, keeps closed, and the exceptional closures of the shop or of that carrier.
 *
 * The calendar the buyer picks a day from and anything that counts delivery days ahead (an
 * estimate of when a parcel arrives) read the same closures through it. Days are calendar days
 * in the time zone PHP runs in, which is the shop's. One call costs two queries at most, the
 * rule and the closures, whatever the length of the period.
 */
final readonly class DeliveryClosedDays
{
    /**
     * The closed days between the two bounds, both included, keyed by date (Y-m-d).
     *
     * @param DeliveryDateRule|null $rule the carrier's rule when the caller already holds it; read otherwise
     *
     * @return array<string, true>
     */
    public function between(int $moduleId, \DateTimeImmutable $first, \DateTimeImmutable $last, ?DeliveryDateRule $rule = null): array
    {
        $first = $first->setTime(0, 0);
        $last = $last->setTime(0, 0);

        if ($last < $first) {
            return [];
        }

        $rule ??= DeliveryDateRuleQuery::create()->findOneByModuleId($moduleId);
        $closedWeekdays = $this->closedWeekdaysOf($rule);
        $closed = $this->closuresBetween($moduleId, $first, $last);

        if ([] !== $closedWeekdays) {
            for ($date = $first; $date <= $last; $date = $date->modify('+1 day')) {
                if (\in_array((int) $date->format('N'), $closedWeekdays, true)) {
                    $closed[$date->format('Y-m-d')] = true;
                }
            }
        }

        ksort($closed);

        return $closed;
    }

    /**
     * The days of the week the carrier does not deliver on: its own list when its rule sets one,
     * even an empty one, and the shop's otherwise.
     *
     * @return list<int>
     */
    public function closedWeekdaysOf(?DeliveryDateRule $rule): array
    {
        return ClosedWeekdays::parse(
            $rule?->getClosedWeekdays() ?? (string) ConfigQuery::read(ClosedWeekdays::SHOP_CONFIG_NAME, ''),
        );
    }

    /**
     * @return array<string, true>
     */
    private function closuresBetween(int $moduleId, \DateTimeImmutable $first, \DateTimeImmutable $last): array
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
}
