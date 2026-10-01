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
 * A module whose post-activation breaks, the way a missing table or a bad
 * seed breaks a real one.
 */
final class FailingPostActivationModule extends BaseModule
{
    public const string FAILURE_MESSAGE = 'The seed data could not be written.';

    public function postActivation(?ConnectionInterface $con = null): void
    {
        throw new \RuntimeException(self::FAILURE_MESSAGE);
    }
}
