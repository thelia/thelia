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

namespace Thelia\Tests\Support\Log;

use Thelia\Log\Destination\TlogDestinationNull;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

/**
 * For a unit test whose code logs through Tlog, which reads its configuration from the
 * database: the test gives it a configuration sending it nowhere and a fresh instance,
 * then drops both, so the instance built here does not serve the tests run after it.
 */
trait SilencedTlog
{
    private function silenceTlog(): void
    {
        ConfigQuery::initCache([Tlog::VAR_DESTINATIONS => TlogDestinationNull::class]);
        self::forgetTlogInstance();
    }

    private function restoreTlog(): void
    {
        ConfigQuery::resetCache();
        self::forgetTlogInstance();
    }

    private static function forgetTlogInstance(): void
    {
        (new \ReflectionProperty(Tlog::class, 'instance'))->setValue(null, null);
    }
}
