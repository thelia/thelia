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

namespace Thelia\Module;

use Thelia\Model\Module;

/**
 * What applying a theme did to one of the modules it requires, kept until every module has
 * been handled so the state reported is the final one.
 *
 * @internal
 */
final readonly class ThemeModuleOutcome
{
    public function __construct(
        public Module $module,
        public bool $installedNow,
        public bool $shipsInactive,
    ) {
    }
}
