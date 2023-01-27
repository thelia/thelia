<?php

namespace Thelia\Api\Bridge\Propel\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class CompositeIdentifiers
{
    public function __construct(
        private array $keys
    ) {

    }
}
