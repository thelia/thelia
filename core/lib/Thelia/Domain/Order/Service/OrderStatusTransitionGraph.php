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

namespace Thelia\Domain\Order\Service;

/**
 * The transitions a merchant allows between order statuses, as a value.
 *
 * A status with no transition leaving it is free: an order holding it may go
 * anywhere, which is the behaviour of every shop before the graph existed. A
 * status with at least one transition leaving it only lets an order reach the
 * statuses it names. Equivalences are resolved before the check: a transition
 * declared from or to a canonical status also covers the custom statuses
 * declared equivalent to it.
 */
final readonly class OrderStatusTransitionGraph
{
    /**
     * @param array<int, list<int>> $targetsByFrom the declared transitions, target status ids by source status id
     * @param array<int, list<int>> $equivalentIds for each status id, the ids that stand for it (itself and its canonical status)
     */
    public function __construct(
        private array $targetsByFrom,
        private array $equivalentIds,
    ) {
    }

    public function isFree(int $fromStatusId): bool
    {
        foreach ($this->equivalents($fromStatusId) as $id) {
            if ([] !== ($this->targetsByFrom[$id] ?? [])) {
                return false;
            }
        }

        return true;
    }

    public function allows(int $fromStatusId, int $toStatusId): bool
    {
        if ($fromStatusId === $toStatusId || $this->isFree($fromStatusId)) {
            return true;
        }

        $targets = $this->targetsFrom($fromStatusId) ?? [];

        foreach ($this->equivalents($toStatusId) as $id) {
            if (\in_array($id, $targets, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The status ids declared reachable from a status, or null when the status is free.
     *
     * @return list<int>|null
     */
    public function targetsFrom(int $fromStatusId): ?array
    {
        if ($this->isFree($fromStatusId)) {
            return null;
        }

        $targets = [];

        foreach ($this->equivalents($fromStatusId) as $id) {
            $targets = [...$targets, ...($this->targetsByFrom[$id] ?? [])];
        }

        return array_values(array_unique($targets));
    }

    /**
     * The statuses nothing leads to: no declared transition targets them, and no
     * other status is free (a free status reaches every other one). The status
     * orders are created in is reachable by definition: the caller decides
     * whether to list it.
     *
     * @param list<int> $statusIds every status of the shop
     *
     * @return list<int>
     */
    public function unreachableStatusIds(array $statusIds): array
    {
        $reached = array_merge([], ...array_values($this->targetsByFrom));
        $unreachable = [];

        foreach ($statusIds as $statusId) {
            $equivalents = $this->equivalents($statusId);

            if ([] !== array_intersect($equivalents, $reached)) {
                continue;
            }

            foreach ($statusIds as $otherStatusId) {
                if (!\in_array($otherStatusId, $equivalents, true) && $this->isFree($otherStatusId)) {
                    continue 2;
                }
            }

            $unreachable[] = $statusId;
        }

        return $unreachable;
    }

    /**
     * @return list<int>
     */
    private function equivalents(int $statusId): array
    {
        return $this->equivalentIds[$statusId] ?? [$statusId];
    }
}
