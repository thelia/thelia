<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;

interface TheliaChoiceFilterInterface
{
    public function getChoiceFilterType(ActiveRecordInterface $activeRecord): ActiveRecordInterface;
}
