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

use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * Each option alone must move the hash, otherwise two renditions that differ by that
     * option alone would be written over one another in the image cache.
     *
     * @return \Generator<string, array{\Closure(ImageEvent): ImageEvent}>
     */
    public static function singleOptionChanges(): \Generator
    {
        yield 'the width' => [static fn (ImageEvent $event): ImageEvent => $event->setWidth(481)];
        yield 'the height' => [static fn (ImageEvent $event): ImageEvent => $event->setHeight(361)];
        yield 'the resize mode' => [static fn (ImageEvent $event): ImageEvent => $event->setResizeMode((string) Image::KEEP_IMAGE_RATIO)];
        yield 'the background color' => [static fn (ImageEvent $event): ImageEvent => $event->setBackgroundColor('#000000')];
        yield 'the effects' => [static fn (ImageEvent $event): ImageEvent => $event->setEffects(['gray_scale', 'mirror'])];
        yield 'the rotation' => [static fn (ImageEvent $event): ImageEvent => $event->setRotation(180)];
        yield 'the zoom' => [static fn (ImageEvent $event): ImageEvent => $event->setAllowZoom(false)];
    }

    /**
     * @param \Closure(ImageEvent): ImageEvent $change
     */
    #[Test]
    #[DataProvider('singleOptionChanges')]
    public function changingOneOptionAloneChangesTheHash(\Closure $change): void
    {
        self::assertNotSame($this->fullyOptioned()->getOptionsHash(), $change($this->fullyOptioned())->getOptionsHash());
    }

    /**
     * The hash names a file in the image cache: it has to stay the same from one release to
     * the next, or every rendition already on disk is orphaned and generated again.
     */
    #[Test]
    public function theHashOfAGivenSetOfOptionsStaysStable(): void
    {
        self::assertSame('c5f6db192d76c8c26c96cad4b1bef9e5', $this->fullyOptioned()->getOptionsHash());
    }

    private function fullyOptioned(): ImageEvent
    {
        return $this->resize(480, 360)
            ->setBackgroundColor('#ffffff')
            ->setEffects(['gray_scale', 'negative'])
            ->setRotation(90)
            ->setAllowZoom(true);
    }

    private function resize(int $width, int $height): ImageEvent
    {
        return (new ImageEvent())
            ->setWidth($width)
            ->setHeight($height)
            ->setResizeMode((string) Image::EXACT_RATIO_WITH_BORDERS);
    }
}
