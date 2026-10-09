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
use Thelia\Domain\DataTransfer\Export\AbstractExport;

/**
 * An export of a module may still extend AbstractExport itself, and give its rows as an
 * array: it is read as it was meant to be.
 */
final class DirectAbstractExportTest extends TestCase
{
    public function testAnExportExtendingTheBaseClassReadsTheRowsItGives(): void
    {
        $export = new class extends AbstractExport {
            protected function getData(): array
            {
                return [['id' => 1], ['id' => 2]];
            }
        };

        self::assertSame([['id' => 1], ['id' => 2]], iterator_to_array($export, false));
    }
}
