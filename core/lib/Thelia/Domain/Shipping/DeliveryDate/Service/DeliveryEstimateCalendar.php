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

    /** @var list<array{string, string}> */
    private array $shopClosedDays = [];

    /** @var array<string, int> */
    private array $slowestDelays = [];

    /** @var array<int, list<array{string, string}>> */
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
     * The runs of days the whole shop is closed from this day on, both bounds included: they
     * stop the preparation and the delivery alike.
     *
     * @return list<array{string, string}>
     */
    public function shopClosedDaysFrom(\DateTimeImmutable $day): array
    {
        $this->loadClosuresFrom($day);

        return $this->shopClosedDays;
    }

    /**
     * The runs of days this carrier does not deliver from this day on, the shop closures included.
     *
     * @return list<array{string, string}>
     */
    public function carrierClosedDaysFrom(int $moduleId, \DateTimeImmutable $day): array
    {
        $this->loadClosuresFrom($day);

        return [...($this->carrierClosedDays[$moduleId] ?? []), ...$this->shopClosedDays];
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
                // A virtual product is not in the parcel.
                ->filterByVirtual(0)
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
        $first = $day->setTime(0, 0)->format('Y-m-d');

        if ($first === $this->closuresFrom) {
            return;
        }

        // Every closure not over yet that an estimate can reach, kept as a run: however long,
        // it is stepped over at once.
        $closures = DeliveryClosureQuery::create()
            ->filterByStartDate($day->setTime(0, 0)->modify(\sprintf('+%d days', DeliveryIntervalCalculator::MAXIMUM_REACH_DAYS))->format('Y-m-d'), Criteria::LESS_EQUAL)
            ->filterByEndDate($first, Criteria::GREATER_EQUAL)
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

            $run = [$start->format('Y-m-d'), $end->format('Y-m-d')];

            if (null === $closure->getModuleId()) {
                $shop[] = $run;
            } else {
                $carriers[(int) $closure->getModuleId()][] = $run;
            }
        }

        $this->shopClosedDays = $shop;
        $this->carrierClosedDays = $carriers;
        $this->closuresFrom = $first;
    }
}
