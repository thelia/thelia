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

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Module\BaseModule;

/**
 * A module that only counts how many times it was post-activated.
 */
final class RecordingPostActivationModule extends BaseModule
{
    public static int $postActivationCount = 0;

    public function postActivation(?ConnectionInterface $con = null): void
    {
        ++self::$postActivationCount;
    }
}
