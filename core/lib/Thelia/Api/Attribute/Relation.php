<?php

namespace Thelia\Api\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Relation
{
    public function __construct(
       private string $targetResource
    ) {

    }
}
