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

namespace Thelia\Tests\Unit\Core\Event\Cache;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Event\Cache\CacheEvent;

/**
 * A cache clear is deferred to the end of the request unless the caller asks otherwise,
 * so that several clears of one directory in a request cost a single one.
 */
final class CacheEventTest extends TestCase
{
    public function testACacheClearWaitsForTheEndOfTheRequestByDefault(): void
    {
        self::assertTrue((new CacheEvent('/var/cache/prod'))->isOnKernelTerminate());
    }

    public function testACallerCanAskForAnImmediateClear(): void
    {
        self::assertFalse((new CacheEvent('/var/cache/prod', false))->isOnKernelTerminate());
    }
}
