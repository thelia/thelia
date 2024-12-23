<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('api.thelia.filter')]
interface TheliaFilterInterface
{
    public function getResourceType(): array;

    public static function getFilterName(): array;

    public function filter(ModelCriteria $query, $value): void;

    public function getValue(ActiveRecordInterface $activeRecord): array;
}
