<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Api\Resource;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Serializer\Annotation\Ignore;

interface ExtendResourceInterface
{
    #[Ignore]
    public static function getResourceToExtend(): string;

    public function extendQuery(ModelCriteria $query);

    // Fill data in extend resource from query results
    public function fillData(ActiveRecordInterface $activeRecord): void;

    public function doSave(): void;
}
