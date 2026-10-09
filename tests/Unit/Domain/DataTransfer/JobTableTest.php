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

namespace Thelia\Tests\Unit\Domain\DataTransfer;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\DataTransfer\Job\JobTable;
use Thelia\Model\Map\ExportJobTableMap;
use Thelia\Model\Map\ImportJobTableMap;

/**
 * The tables the claim and the signs of life update are the tables of the job rows.
 */
final class JobTableTest extends TestCase
{
    public function testEachJobTableIsTheTableOfItsRows(): void
    {
        self::assertSame(ExportJobTableMap::TABLE_NAME, JobTable::Export->value);
        self::assertSame(ImportJobTableMap::TABLE_NAME, JobTable::Import->value);
    }

    public function testAJobIsNamedAsTheFailedJobsScreenNamesIt(): void
    {
        self::assertSame('Export #4', JobTable::Export->describe(4));
        self::assertSame('Import #12', JobTable::Import->describe(12));
    }
}
