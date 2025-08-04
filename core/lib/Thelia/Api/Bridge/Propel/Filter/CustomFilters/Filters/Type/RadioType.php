<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Type;

use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterTypeInterface;

class RadioType implements TheliaFilterTypeInterface
{
    public static function getName(): string
    {
        return 'radio';
    }
}
