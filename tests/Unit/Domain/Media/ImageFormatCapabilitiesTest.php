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

use Imagine\Gmagick\Imagine as GmagickImagine;
use Imagine\Image\ImagineInterface;
use Imagine\Imagick\Imagine as ImagickImagine;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Media\Enum\ImageFormat;
use Thelia\Domain\Media\Service\ImageFormatCapabilities;

final class ImageFormatCapabilitiesTest extends TestCase
{
    /**
     * Without an Imagine instance, GD is assumed: what the core reports must be what GD
     * says it can write on this very server, format by format and in the offered order.
     */
    public function testTheReportedFormatsAreTheOnesGdCanWriteHere(): void
    {
        $capabilities = new ImageFormatCapabilities();

        $gdWrites = static fn (string $constant): bool => \function_exists('imagetypes')
            && \defined($constant)
            && (imagetypes() & \constant($constant)) !== 0;

        self::assertSame('gd', $capabilities->driver());
        self::assertSame($gdWrites('IMG_WEBP'), $capabilities->supports(ImageFormat::Webp));
        self::assertSame($gdWrites('IMG_AVIF'), $capabilities->supports(ImageFormat::Avif));
        self::assertSame(
            array_values(array_filter([
                $gdWrites('IMG_AVIF') ? ImageFormat::Avif : null,
                $gdWrites('IMG_WEBP') ? ImageFormat::Webp : null,
            ])),
            $capabilities->supportedFormats()
        );
    }

    /**
     * The driver is read off the Imagine instance LiipImagine encodes with.
     */
    public function testTheDriverIsTheOneTheImagineInstanceIs(): void
    {
        self::assertSame('imagick', (new ImageFormatCapabilities($this->createStub(ImagickImagine::class)))->driver());
        self::assertSame('gmagick', (new ImageFormatCapabilities($this->createStub(GmagickImagine::class)))->driver());
        self::assertSame('gd', (new ImageFormatCapabilities($this->createStub(ImagineInterface::class)))->driver());
    }
}
