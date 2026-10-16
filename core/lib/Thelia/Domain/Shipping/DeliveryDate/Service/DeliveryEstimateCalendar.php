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
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\DeliveryClosureQuery;
use Thelia\Model\DeliveryDateRule;
use Thelia\Model\DeliveryDateRuleQuery;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductQuery;

/**
 * What a delivery estimate reads, loaded once per request: the delays of every carrier in
 * one query, the closures from today on in another, and the shop settings. A delivery step
 * listing several carriers, or a product page comparing them, then costs no query per
 * carrier for its estimates.
 *
 * DeliveryDateSettings forgets it on every write, and the container on every reset between
 * two requests of the same process.
 */
final class DeliveryEstimateCalendar implements ResetInterface
{
    /** @var array<int, DeliveryDateRule>|null */
    private ?array $rules = null;

    /** @var list<int>|null */
    private ?array $shopClosedWeekdays = null;

    private string|false|null $cutoff = false;

    private ?string $closuresFrom = null;

    /** @var array<string, true> */
    private array $shopClosedDays = [];

    /** @var array<string, int> */
    private array $slowestDelays = [];

    /** @var array<int, array<string, true>> */
    private array $carrierClosedDays = [];

    public function ruleOf(int $moduleId): ?DeliveryDateRule
    {
        if (null === $this->rules) {
            $rules = [];

            foreach (DeliveryDateRuleQuery::create()->find() as $rule) {
                $rules[(int) $rule->getModuleId()] = $rule;
            }

            $this->rules = $rules;
        }

        return $this->rules[$moduleId] ?? null;
    }

    /**
     * @return list<int>
     */
    public function shopClosedWeekdays(): array
    {
        return $this->shopClosedWeekdays ??= ClosedWeekdays::parse((string) ConfigQuery::read(ClosedWeekdays::SHOP_CONFIG_NAME, ''));
    }

    /**
     * The cutoff hour of the shop, or null when it has none or holds something else than an hour.
     */
    public function cutoff(): ?string
    {
        if (false === $this->cutoff) {
            $stored = trim((string) ConfigQuery::read(DeliveryTimeEstimator::CUTOFF_CONFIG_NAME, ''));
            $this->cutoff = 1 === preg_match(DeliveryTimeEstimator::CUTOFF_PATTERN, $stored) ? $stored : null;
        }

        return $this->cutoff;
    }

    /**
     * The days the whole shop is closed from this day on, keyed Y-m-d: they stop the
     * preparation and the delivery alike.
     *
     * @return array<string, true>
     */
    public function shopClosedDaysFrom(\DateTimeImmutable $day): array
    {
        $this->loadClosuresFrom($day);

        return $this->shopClosedDays;
    }

    /**
     * The days this carrier does not deliver from this day on, the shop closures included.
     *
     * @return array<string, true>
     */
    public function carrierClosedDaysFrom(int $moduleId, \DateTimeImmutable $day): array
    {
        $this->loadClosuresFrom($day);

        return ($this->carrierClosedDays[$moduleId] ?? []) + $this->shopClosedDays;
    }

    /**
     * The availability delay of the slowest of these products, in working days, read once
     * per set of products.
     *
     * @param list<int> $productIds
     */
    public function slowestAvailabilityDelayOf(array $productIds): int
    {
        $productIds = array_values(array_unique($productIds));
        sort($productIds);

        if ([] === $productIds) {
            return 0;
        }

        $key = implode(',', $productIds);

        if (!isset($this->slowestDelays[$key])) {
            $slowest = ProductQuery::create()
                ->filterById($productIds, Criteria::IN)
                ->withColumn('MAX('.ProductTableMap::COL_AVAILABILITY_DELAY_DAYS.')', 'slowest')
                ->select(['slowest'])
                ->findOne();

            $this->slowestDelays[$key] = max(0, (int) $slowest);
        }

        return $this->slowestDelays[$key];
    }

    public function reset(): void
    {
        $this->slowestDelays = [];
        $this->rules = null;
        $this->shopClosedWeekdays = null;
        $this->cutoff = false;
        $this->closuresFrom = null;
        $this->shopClosedDays = [];
        $this->carrierClosedDays = [];
    }

    private function loadClosuresFrom(\DateTimeImmutable $day): void
    {
        $first = $day->setTime(0, 0);

        if ($first->format('Y-m-d') === $this->closuresFrom) {
            return;
        }

        $last = $first->modify(\sprintf('+%d days', 2 * DeliveryIntervalCalculator::MAXIMUM_WALK_DAYS));
        $closures = DeliveryClosureQuery::create()
            ->filterByStartDate($last->format('Y-m-d'), Criteria::LESS_EQUAL)
            ->filterByEndDate($first->format('Y-m-d'), Criteria::GREATER_EQUAL)
            ->find();

        // Noted as loaded once the query answered: a query that fails leaves the next estimate
        // to ask again, not to count without the closures.
        $shop = [];
        $carriers = [];

        foreach ($closures as $closure) {
            $start = DeliveryDateGuard::parseDay((string) $closure->getStartDate('Y-m-d'));
            $end = DeliveryDateGuard::parseDay((string) $closure->getEndDate('Y-m-d'));

            if (null === $start || null === $end) {
                continue;
            }

            for ($date = max($first, $start), $to = min($last, $end); $date <= $to; $date = $date->modify('+1 day')) {
                if (null === $closure->getModuleId()) {
                    $shop[$date->format('Y-m-d')] = true;
                } else {
                    $carriers[(int) $closure->getModuleId()][$date->format('Y-m-d')] = true;
                }
            }
        }

        $this->shopClosedDays = $shop;
        $this->carrierClosedDays = $carriers;
        $this->closuresFrom = $first->format('Y-m-d');
    }
}
