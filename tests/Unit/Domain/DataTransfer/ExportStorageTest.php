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
use Thelia\Domain\DataTransfer\Export\ExportStorage;

final class ExportStorageTest extends TestCase
{
    public function testANameIsWrittenWithTheLettersOfTheFolder(): void
    {
        self::assertSame('export_clients_t', ExportStorage::safeName('export clients été'));
        self::assertSame('export', ExportStorage::safeName('../..'));
    }

    /**
     * A file system takes names of 255 bytes at most: the name of a module, however
     * long, leaves room for the date, the unique part and the extension.
     */
    public function testALongNameIsCut(): void
    {
        self::assertSame(100, \strlen(ExportStorage::safeName(str_repeat('a', 300))));
    }
}
