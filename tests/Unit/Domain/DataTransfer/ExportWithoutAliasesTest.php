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
use Thelia\Domain\DataTransfer\Export\ArrayAbstractExport;
use Thelia\Domain\DataTransfer\Export\JsonFileAbstractExport;

/**
 * `$orderAndAliases` defaults to an empty array, so an export declaring no
 * alias used to be turned by ExportHandler into one empty line per record.
 */
final class ExportWithoutAliasesTest extends TestCase
{
    private const ROW = ['ref' => 'A1', 'title' => 'First'];

    public function testAnArrayExportWithoutAliasesKeepsItsColumns(): void
    {
        $export = new class extends ArrayAbstractExport {
            protected function getData(): array
            {
                return [];
            }
        };

        self::assertSame(self::ROW, $export->applyOrderAndAliases(self::ROW));
    }

    public function testAJsonFileExportWithoutAliasesKeepsItsColumns(): void
    {
        $export = new class extends JsonFileAbstractExport {
            protected function getData(): string
            {
                return '';
            }
        };

        self::assertSame(self::ROW, $export->applyOrderAndAliases(self::ROW));
    }

    public function testDeclaredAliasesStillSelectAndRenameTheColumns(): void
    {
        $export = new class extends ArrayAbstractExport {
            protected array $orderAndAliases = ['title' => 'Label', 'missing' => 'Absent'];

            protected function getData(): array
            {
                return [];
            }
        };

        self::assertSame(['Label' => 'First', 'Absent' => null], $export->applyOrderAndAliases(self::ROW));
    }
}
