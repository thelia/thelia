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

namespace HookModuleProbe;

use Thelia\Module\BaseModule;

/**
 * Stands in for the module a hook belongs to.
 *
 * It lives outside the Thelia namespace on purpose: a hook resolves its module
 * from the first segment of its own class name, so only a class named after a
 * module code exercises that path.
 */
class HookModuleProbe extends BaseModule
{
}
