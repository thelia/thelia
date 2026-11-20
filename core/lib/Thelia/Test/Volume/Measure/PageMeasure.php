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

namespace Thelia\Test\Volume\Measure;

/**
 * One call of a page: its status, the statements the database received, its times and its size.
 */
final readonly class PageMeasure
{
    public function __construct(
        public int $status,
        public int $queries,
        public float $firstByteSeconds,
        public float $totalSeconds,
        public int $bytes,
    ) {
    }
}
