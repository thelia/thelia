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

namespace Thelia\Domain\Catalog\Restriction;

/**
 * The two senses of a catalog restriction (catalog_restriction.mode).
 */
enum CatalogRestrictionMode: string
{
    /** Once a customer has one allow rule, whatever no allow rule opens is closed. */
    case Allow = 'allow';

    /** The target is closed, and wins over any allow rule. */
    case Deny = 'deny';
}
