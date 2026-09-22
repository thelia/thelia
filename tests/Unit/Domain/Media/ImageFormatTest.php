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

namespace Thelia\Tests\Unit\Domain\Media;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Media\Enum\ImageFormat;

/**
 * The formats a shop offers on top of the source one.
 *
 * The setting is a comma-separated string in the `config` table, which a merchant may
 * edit by hand and a newer Thelia may write formats into that this version has never
 * heard of. None of that may take a catalogue page down: what is not understood is
 * dropped, and a shop left with nothing simply serves the source format.
 */
final class ImageFormatTest extends TestCase
{
    public function testAMissingOrEmptySettingOffersNoModernFormat(): void
    {
        self::assertSame([], ImageFormat::listFromStoredValue(null));
        self::assertSame([], ImageFormat::listFromStoredValue(''));
        self::assertSame([], ImageFormat::listFromStoredValue('   '));
    }

    public function testAnUnknownFormatIsDroppedRatherThanRefused(): void
    {
        self::assertSame([], ImageFormat::listFromStoredValue('jxl'));
        self::assertSame([ImageFormat::Webp], ImageFormat::listFromStoredValue('jxl,webp'));
    }

    public function testEntriesAreReadCaseInsensitivelyAndTrimmed(): void
    {
        self::assertSame([ImageFormat::Webp], ImageFormat::listFromStoredValue(' WebP '));
        self::assertSame([ImageFormat::Avif, ImageFormat::Webp], ImageFormat::listFromStoredValue('AVIF, webp'));
    }

    /**
     * The order is the enum's, not the merchant's: a browser is offered the most
     * efficient format first, whatever order the setting happens to be written in.
     */
    public function testFormatsComeBackMostEfficientFirst(): void
    {
        self::assertSame([ImageFormat::Avif, ImageFormat::Webp], ImageFormat::listFromStoredValue('webp,avif'));
    }

    public function testADuplicatedEntryIsListedOnce(): void
    {
        self::assertSame([ImageFormat::Webp], ImageFormat::listFromStoredValue('webp,webp'));
    }

    public function testAListSurvivesARoundTripThroughTheStoredValue(): void
    {
        $stored = ImageFormat::toStoredValue([ImageFormat::Webp, ImageFormat::Avif]);

        self::assertSame('avif,webp', $stored);
        self::assertSame([ImageFormat::Avif, ImageFormat::Webp], ImageFormat::listFromStoredValue($stored));
        self::assertSame('', ImageFormat::toStoredValue([]));
    }

    /**
     * The extension is what the web server reads to pick the Content-Type it sends, and
     * the media type is what the <source> announces. A mismatch between the two is a
     * browser refusing the image it was promised.
     */
    public function testEachFormatCarriesMatchingExtensionAndMediaType(): void
    {
        foreach (ImageFormat::cases() as $format) {
            self::assertSame($format->value, $format->extension());
            self::assertSame('image/'.$format->extension(), $format->mimeType());
        }
    }

    public function testEachFormatHasItsOwnQualityScaleAndSetting(): void
    {
        foreach (ImageFormat::cases() as $format) {
            self::assertGreaterThanOrEqual(1, $format->defaultQuality());
            self::assertLessThanOrEqual(100, $format->defaultQuality());
            self::assertSame('image_quality_'.$format->value, $format->qualityConfigName());
        }

        // Not a trivia check: the same number is not the same picture from one encoder
        // to the next, and a shared default would hand AVIF a JPEG's quality.
        self::assertNotSame(ImageFormat::Avif->defaultQuality(), ImageFormat::Webp->defaultQuality());
    }
}
