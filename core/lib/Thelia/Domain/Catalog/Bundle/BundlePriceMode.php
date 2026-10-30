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

enum BundlePriceMode: string
{
    /** The price is the one of the sale element of the bundle. */
    case Fixed = 'fixed';

    /** The price is the sum of the components, less the discount of the bundle. */
    case Computed = 'computed';
}
