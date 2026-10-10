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

use Propel\Runtime\Propel;
use Thelia\Domain\DataTransfer\Import\AbstractImport;
use Thelia\Model\Map\ImportTableMap;

/**
 * An import whose save of a row fails and is caught, as a module may write it: the
 * transaction the save opened is rolled back inside the import's.
 */
final class RowRollingBackImport extends AbstractImport
{
    protected array $mandatoryColumns = ['id'];

    public function importData(array $data): ?string
    {
        $connection = Propel::getWriteConnection(ImportTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        $connection->rollBack();

        return 'Row refused.';
    }
}
