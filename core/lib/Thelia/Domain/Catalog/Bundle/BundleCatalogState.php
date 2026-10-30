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

namespace Thelia\Domain\Catalog\Bundle;

use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductQuery;

/**
 * Whether the shop sells a bundle at all, so that a shop without one pays nothing for the
 * bundles on its hot paths (catalog loops, the API reads of sale elements, the addition to
 * the cart, the pricing of a cart).
 *
 * The answer is one variable of the configuration table, which the configuration cache loads
 * once for every request: reading it costs no query. It is written by the model of the
 * product whenever a product becomes or stops being a bundle, or changes its price mode
 * (Thelia\Model\Product), so every writer is covered: the admin API, the back office, the
 * clone, the imports. A database that never had the variable has no bundle.
 *
 * Stored as '0' (no bundle), '1' (bundles, none priced by its components) or '2' (at least one
 * bundle priced by its components).
 */
final readonly class BundleCatalogState
{
    public const CONFIG_KEY = 'catalog_has_bundles';

    private const NONE = 0;

    private const FIXED_ONLY = 1;

    private const COMPUTED = 2;

    public function hasBundles(): bool
    {
        return self::NONE !== $this->level();
    }

    public function hasComputedBundles(): bool
    {
        return self::COMPUTED === $this->level();
    }

    /**
     * Recounts the bundles of the catalog and stores the answer (two COUNT). Nothing is written,
     * and so no configuration cache is rebuilt, when the answer did not change.
     */
    public function refresh(): void
    {
        $level = self::NONE;

        if (ProductQuery::create()->filterByIsBundle(1)->count() > 0) {
            $level = ProductQuery::create()->filterByIsBundle(1)->filterByBundlePriceMode(BundlePriceMode::Computed->value)->count() > 0
                ? self::COMPUTED
                : self::FIXED_ONLY;
        }

        if ($level !== $this->level()) {
            ConfigQuery::write(self::CONFIG_KEY, (string) $level);
        }
    }

    private function level(): int
    {
        $stored = ConfigQuery::read(self::CONFIG_KEY, '0');

        // '0' is a value, not an absence: ConfigQuery::read() answers it as such, and the
        // comparison is on the number, never on the truthiness of the string.
        return \is_string($stored) && is_numeric($stored) ? (int) $stored : self::NONE;
    }
}
