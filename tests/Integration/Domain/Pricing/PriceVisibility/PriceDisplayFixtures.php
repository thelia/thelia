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

namespace Thelia\Tests\Integration\Domain\Pricing\PriceVisibility;

use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;

/**
 * The price display mode of the shop and of a product, written the way the back office writes
 * them, and forgotten with the test: the shop reads its settings once per process.
 */
trait PriceDisplayFixtures
{
    protected function shopShows(PriceDisplayMode $mode): void
    {
        ConfigQuery::write(PriceDisplayMode::CONFIG_KEY, $mode->value, false);
        $this->forgetPriceDisplayMemos();
    }

    protected function productShows(Product $product, ?PriceDisplayMode $mode): void
    {
        $product->setPriceDisplayMode($mode?->value)->save($this->getPropelConnection());
        $this->forgetPriceDisplayMemos();
    }

    protected function forgetPriceDisplayMemos(): void
    {
        ConfigQuery::resetCache();
        $this->getService('services_resetter')->reset();
    }
}
