<?php

namespace Thelia\Api\Resource;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;

interface ExtendResourceInterface
{
   public static function getResourceToExtend(): string;

    public function extendQuery(ModelCriteria $query);

    // Fill data in extend resource from query results
    public function fillData(ActiveRecordInterface $activeRecord): void;

    public function doSave(): void;
}
