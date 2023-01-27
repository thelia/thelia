<?php

namespace Thelia\Api\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class CompositeIdentifiers
{
    public function __construct(
        private array $keys
    ) {

    }
}
