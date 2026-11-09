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

namespace Thelia\Domain\Report\Sales;

/**
 * The orders a sales report covers: the ones created between two moments, both included and
 * read to the second, in the given status scope. The sale units narrow the orders to the
 * ones placed on them, as the back office narrows the orders an administrator reaches: an
 * order placed on no sale unit (a national order) is then left out, and an empty list
 * reaches no order at all. Null narrows nothing.
 */
final readonly class SalesReportCriteria
{
    /** @param list<int>|null $saleUnitIds */
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        public StatusScope $scope = StatusScope::Paid,
        public ?array $saleUnitIds = null,
    ) {
        if ($from > $to) {
            throw new \InvalidArgumentException(\sprintf('A sales report period cannot end (%s) before it starts (%s).', $to->format('Y-m-d H:i:s'), $from->format('Y-m-d H:i:s')));
        }
    }

    /**
     * The period of the same length that ends the second before this one starts, on the same
     * orders: the one each figure of the report is compared with.
     */
    public function previous(): self
    {
        $previousTo = $this->from->modify('-1 second');

        return new self($this->previousFrom($previousTo), $previousTo, $this->scope, $this->saleUnitIds);
    }

    /**
     * A period of whole days goes back as many days of the calendar: counted in seconds, a
     * period over a change of clock would start an hour off midnight. Any other period goes
     * back its length in seconds.
     */
    private function previousFrom(\DateTimeImmutable $previousTo): \DateTimeImmutable
    {
        $end = $this->to->modify('+1 second');
        $days = (int) $this->from->diff($end)->days;

        if ($days > 0 && $this->from->modify(\sprintf('+%d days', $days)) == $end) {
            return $this->from->modify(\sprintf('-%d days', $days));
        }

        return $previousTo->modify(\sprintf('-%d seconds', $this->to->getTimestamp() - $this->from->getTimestamp()));
    }
}
