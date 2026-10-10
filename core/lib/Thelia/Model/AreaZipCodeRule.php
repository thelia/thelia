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

namespace Thelia\Model;

use Thelia\Model\Base\AreaZipCodeRule as BaseAreaZipCodeRule;

/**
 * A postal code rule of a delivery zone: a pattern (see ZipCodePattern) that includes or
 * excludes the addresses it matches, for one country of the zone or for all of them.
 */
class AreaZipCodeRule extends BaseAreaZipCodeRule
{
}
