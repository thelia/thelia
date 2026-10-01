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

namespace Thelia\Core\Event\Product;

use Thelia\Core\Event\ActionEvent;

/**
 * Raised by a front theme once per product search a shopper submits, with the number of
 * products found, so a module can keep a search log whatever runs the search. The event
 * is dispatched under its class name: a listener subscribes to ProductSearchedEvent::class,
 * which resolves without loading the class on a shop that does not have it yet.
 *
 * Suggestions shown while typing and further pages of the same results are not searches.
 */
class ProductSearchedEvent extends ActionEvent
{
    public function __construct(
        private readonly string $term,
        private readonly string $locale,
        private readonly int $hits,
    ) {
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getHits(): int
    {
        return $this->hits;
    }
}
