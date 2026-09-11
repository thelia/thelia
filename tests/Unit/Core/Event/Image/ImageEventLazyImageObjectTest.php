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

namespace Thelia\Tests\Unit\Core\Event\Image;

use Imagine\Image\ImageInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Thelia\Core\Event\Image\ImageEvent;

/**
 * {@see ImageEvent::getImageObject()} decodes the cache file it was handed only
 * once something actually asks for it: uploading a file or transforming an API
 * resource never does, and paid for a GD decode on every single one regardless.
 */
final class ImageEventLazyImageObjectTest extends TestCase
{
    #[Test]
    public function settingTheSupplierDoesNotCallIt(): void
    {
        $calls = 0;

        (new ImageEvent())->setImageObjectSupplier(function () use (&$calls): ImageInterface {
            ++$calls;

            return $this->createStub(ImageInterface::class);
        });

        self::assertSame(0, $calls);
    }

    #[Test]
    public function theSupplierRunsExactlyOnceHoweverManyTimesTheImageIsAskedFor(): void
    {
        $calls = 0;
        $image = $this->createStub(ImageInterface::class);

        $event = (new ImageEvent())->setImageObjectSupplier(static function () use (&$calls, $image): ImageInterface {
            ++$calls;

            return $image;
        });

        $first = $event->getImageObject();
        $second = $event->getImageObject();

        self::assertSame(1, $calls);
        self::assertSame($image, $first);
        self::assertSame($image, $second);
    }

    #[Test]
    public function anExplicitImageWinsOverAPendingSupplierRatherThanBeingLayeredUnderIt(): void
    {
        $calls = 0;

        $event = (new ImageEvent())->setImageObjectSupplier(function () use (&$calls): ImageInterface {
            ++$calls;

            return $this->createStub(ImageInterface::class);
        });

        $event->setImageObject(null);

        self::assertNull($event->getImageObject());
        self::assertSame(0, $calls);
    }
}
