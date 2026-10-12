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

namespace Thelia\Tests\Unit\Core\Event\Brand;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Event\Brand\BrandUpdateEvent;

/**
 * An update event is raised for a brand id and carries no brand model until the action
 * has loaded one.
 */
final class BrandUpdateEventTest extends TestCase
{
    public function testAnUpdateEventKnowsItsBrandIdButNoBrandModelYet(): void
    {
        $event = new BrandUpdateEvent(12);

        self::assertSame(12, $event->getBrandId());
        self::assertFalse($event->hasBrand());
        self::assertNull($event->getBrand());
    }
}
