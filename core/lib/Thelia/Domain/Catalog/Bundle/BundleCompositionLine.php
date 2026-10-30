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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The quantity chosen of one component of a bundle, the component named by its bundle item.
 */
#[Exclude]
final readonly class BundleCompositionLine
{
    /**
     * @param string $quantity a positive decimal without trailing zeros ("2", "0.5")
     */
    public function __construct(
        public int $bundleItemId,
        public string $quantity,
    ) {
    }
}
