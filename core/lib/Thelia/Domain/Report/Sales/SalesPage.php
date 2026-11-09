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
 * One page of the rows of a sales report.
 *
 * @template T
 */
final readonly class SalesPage
{
    /** @param list<T> $rows */
    public function __construct(
        public array $rows,
        public int $totalRows,
        public int $page,
        public int $perPage,
    ) {
    }

    /**
     * The number of pages the rows fill, at least one: an empty report still shows its page.
     */
    public function pageCount(): int
    {
        if ($this->totalRows <= 0 || $this->perPage <= 0) {
            return 1;
        }

        return intdiv($this->totalRows + $this->perPage - 1, $this->perPage);
    }
}
