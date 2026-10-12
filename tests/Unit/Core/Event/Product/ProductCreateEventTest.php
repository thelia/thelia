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

namespace Thelia\Tests\Unit\Core\Event\Product;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Event\Product\ProductCreateEvent;

/**
 * Visibility and virtuality come from a form checkbox: a box that is ticked or left
 * unticked must come back as a real boolean, and an absent field as "not visible".
 */
final class ProductCreateEventTest extends TestCase
{
    public function testAFreshEventHasNotBeenToldAnythingAboutVisibilityOrVirtuality(): void
    {
        $event = new ProductCreateEvent();

        self::assertNull($event->getVisible());
        self::assertNull($event->getVirtual());
    }

    public function testVisibilityFromAFormIsReadBackAsABoolean(): void
    {
        self::assertTrue((new ProductCreateEvent())->setVisible(1)->getVisible());
        self::assertFalse((new ProductCreateEvent())->setVisible(0)->getVisible());
    }

    public function testAnAbsentVisibilityFieldMeansNotVisible(): void
    {
        self::assertFalse((new ProductCreateEvent())->setVisible(null)->getVisible());
    }

    public function testVirtualityFromAFormIsReadBackAsABoolean(): void
    {
        self::assertTrue((new ProductCreateEvent())->setVirtual(1)->getVirtual());
        self::assertFalse((new ProductCreateEvent())->setVirtual(0)->getVirtual());
    }

    public function testAnAbsentVirtualityFieldMeansNotVirtual(): void
    {
        self::assertFalse((new ProductCreateEvent())->setVirtual(null)->getVirtual());
    }
}
