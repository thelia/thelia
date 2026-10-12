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

namespace Thelia\Tests\Unit\Api\Bridge\Propel\Service\Fixture;

use Propel\Runtime\Map\TableMap;

/**
 * The schema of the recording product, built in memory with the NOT NULL columns of the real
 * product table that the transformer meets: id, ref and visible. Nothing reads the global
 * Propel database map.
 */
final class RecordingProductTableMap
{
    public static function getTableMap(): TableMap
    {
        $tableMap = new TableMap('product');
        $tableMap->addColumn('id', 'Id', 'INTEGER', true, null, null, true);
        $tableMap->addColumn('ref', 'Ref', 'VARCHAR', true, 255);
        $tableMap->addColumn('visible', 'Visible', 'TINYINT', true, null, 0);

        return $tableMap;
    }
}
