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

namespace Thelia\Tests\Support\Module;

use Thelia\Module\BaseModule;

/**
 * A module that only reads its configuration, the way a payment module reads its keys.
 */
final class SaleUnitConfigProbe extends BaseModule
{
}
