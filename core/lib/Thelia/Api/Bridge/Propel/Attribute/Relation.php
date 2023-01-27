<?php

namespace Thelia\Api\Bridge\Propel\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Relation
{
    public function __construct(
       private string $targetResource
    ) {

    }
}
