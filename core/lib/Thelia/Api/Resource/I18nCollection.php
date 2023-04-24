<?php

namespace Thelia\Api\Resource;

use ArrayIterator;
use IteratorAggregate;

class I18nCollection implements IteratorAggregate
{
     public array $i18ns;

    public function __construct()
    {
        $this->i18ns = [];
    }

    public function add(I18n $i18n, string $locale): self
    {
        $this->i18ns[$locale] = $i18n;

        return $this;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->i18ns);
    }
}
