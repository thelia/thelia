<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('api.thelia.filter.type')]
interface TheliaFilterTypeInterface
{
    public static function getName(): string;
}
