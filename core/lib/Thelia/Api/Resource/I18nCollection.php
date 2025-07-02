<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Api\Resource;

use IteratorAggregate;
use ArrayIterator;

class I18nCollection implements IteratorAggregate
{
    public array $i18ns = [];

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
