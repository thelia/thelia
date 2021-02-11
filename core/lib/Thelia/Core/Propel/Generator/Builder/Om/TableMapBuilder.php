<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Propel\Generator\Builder\Om;

use Propel\Generator\Builder\Om\TableMapBuilder as PropelTableMapBuilder;
use Thelia\Core\Propel\Generator\Builder\Om\Mixin\ImplementationClassTrait;

class TableMapBuilder extends PropelTableMapBuilder
{
    use ImplementationClassTrait;
}
