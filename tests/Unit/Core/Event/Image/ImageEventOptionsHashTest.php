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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Thelia\Action\Image;
use Thelia\Core\Event\Image\ImageEvent;

/**
 * {@see ImageEvent::getOptionsHash()} names the cache file of a processed image, next to the
 * source name. Two renditions that differ in any option must therefore never share a hash, and
 * the hash must never be empty: an empty one puts every rendition of an image in the same file.
 */
final class ImageEventOptionsHashTest extends TestCase
{
    #[Test]
    public function aResizeWithoutBackgroundColorStillGetsAHash(): void
    {
        $event = $this->resize(480, 360);

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $event->getOptionsHash());
    }

    #[Test]
    public function twoSizesOfTheSameImageGetTwoHashes(): void
    {
        self::assertNotSame(
            $this->resize(480, 360)->getOptionsHash(),
            $this->resize(100, 100)->getOptionsHash(),
        );
    }

    #[Test]
    public function theBackgroundColorIsPartOfTheHash(): void
    {
        $withoutBackground = $this->resize(480, 360);
        $withBackground = $this->resize(480, 360)->setBackgroundColor('#ffffff');

        self::assertNotSame($withoutBackground->getOptionsHash(), $withBackground->getOptionsHash());
    }

    #[Test]
    public function theSameOptionsGiveTheSameHash(): void
    {
        self::assertSame(
            $this->resize(480, 360)->getOptionsHash(),
            $this->resize(480, 360)->getOptionsHash(),
        );
    }

    private function resize(int $width, int $height): ImageEvent
    {
        return (new ImageEvent())
            ->setWidth($width)
            ->setHeight($height)
            ->setResizeMode((string) Image::EXACT_RATIO_WITH_BORDERS);
    }
}
