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

namespace Thelia\Domain\Media\Service;

use Thelia\Domain\Media\Enum\ImageFormat;
use Thelia\Model\ConfigQuery;

/**
 * Which image formats this server can actually write.
 *
 * A format is not a setting the shop is free to invent: it is a capability of the
 * graphics library PHP was built against. GD writes AVIF only from PHP 8.1 built with a
 * recent libavif, and a shared host may ship neither Imagick nor AVIF at all. Asking
 * here, rather than trying and catching, is what lets the back office tell a merchant
 * why a format is missing instead of showing them a broken image.
 */
final readonly class ImageFormatCapabilities
{
    /**
     * The formats this server can write, in the order a browser is offered them.
     *
     * @return list<ImageFormat>
     */
    public function supportedFormats(): array
    {
        return array_values(array_filter(ImageFormat::cases(), fn (ImageFormat $format): bool => $this->supports($format)));
    }

    public function supports(ImageFormat $format): bool
    {
        return match ($this->driver()) {
            'imagick' => $this->imageMagickWrites('Imagick', $format),
            'gmagick' => $this->imageMagickWrites('Gmagick', $format),
            default => $this->gdWrites($format),
        };
    }

    /**
     * The graphics library the shop is configured to use.
     *
     * The same setting drives the core's own image pipeline, so a shop that switches
     * driver changes what it can produce in one place.
     */
    public function driver(): string
    {
        return strtolower((string) ConfigQuery::read('imagine_graphic_driver', 'gd'));
    }

    private function gdWrites(ImageFormat $format): bool
    {
        if (!\function_exists('imagetypes')) {
            return false;
        }

        $constant = match ($format) {
            ImageFormat::Avif => 'IMG_AVIF',
            ImageFormat::Webp => 'IMG_WEBP',
        };

        // IMG_AVIF only exists from PHP 8.1, and only where GD was built with it.
        if (!\defined($constant)) {
            return false;
        }

        return (imagetypes() & \constant($constant)) !== 0;
    }

    private function imageMagickWrites(string $extensionClass, ImageFormat $format): bool
    {
        if (!class_exists($extensionClass)) {
            return false;
        }

        $queryFormats = [$extensionClass, 'queryFormats'];

        if (!\is_callable($queryFormats)) {
            return false;
        }

        return [] !== $queryFormats(strtoupper($format->value));
    }
}
