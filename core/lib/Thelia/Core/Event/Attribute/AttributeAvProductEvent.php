<?php

namespace Thelia\Core\Event\Attribute;

use Thelia\Core\Event\ActionEvent;

class AttributeAvProductEvent extends ActionEvent
{
    private array $attributes = [];

    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): AttributeAvProductEvent
    {
        $this->attributes = $attributes;
        return $this;
    }
}
