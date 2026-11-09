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

namespace Thelia\Tests\Unit\Domain\Report\Sales;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Report\Sales\SalesPage;

final class SalesPageTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function pages(): iterable
    {
        yield 'no row still shows its page' => [0, 25, 1];
        yield 'fewer rows than a page' => [3, 25, 1];
        yield 'exactly one page' => [25, 25, 1];
        yield 'one row over a page' => [26, 25, 2];
        yield 'one row a page' => [7, 1, 7];
    }

    #[DataProvider('pages')]
    public function testThePageCountCoversEveryRow(int $totalRows, int $perPage, int $expected): void
    {
        self::assertSame($expected, (new SalesPage([], $totalRows, 1, $perPage))->pageCount());
    }
}
