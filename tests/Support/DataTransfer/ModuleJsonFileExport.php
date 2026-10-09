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

namespace Thelia\Tests\Support\DataTransfer;

use Thelia\Domain\DataTransfer\Export\JsonFileAbstractExport;

/**
 * An export of a module that reads its rows from a JSON file the module keeps.
 */
final class ModuleJsonFileExport extends JsonFileAbstractExport
{
    public static string $rowsFile = '';

    protected function getData(): string
    {
        return self::$rowsFile;
    }
}
