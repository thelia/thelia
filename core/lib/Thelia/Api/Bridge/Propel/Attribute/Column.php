<?php

namespace Thelia\Api\Bridge\Propel\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        private ?string $propelFieldName = null
    ) {

    }
}
