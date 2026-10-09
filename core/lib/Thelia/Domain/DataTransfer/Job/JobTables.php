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

namespace Thelia\Domain\DataTransfer\Job;

use Thelia\Model\Map\ExportJobTableMap;
use Thelia\Model\Map\ImportJobTableMap;

/**
 * The tables the export and import jobs live in: the only table names ever written into
 * the SQL that takes, marks or keeps a job alive.
 */
final class JobTables
{
    public const EXPORT = ExportJobTableMap::TABLE_NAME;
    public const IMPORT = ImportJobTableMap::TABLE_NAME;

    /**
     * @phpstan-assert 'export_job'|'import_job' $table
     */
    public static function assert(string $table): void
    {
        if (self::EXPORT !== $table && self::IMPORT !== $table) {
            throw new \InvalidArgumentException('The table given is not a table of export or import jobs.');
        }
    }
}
