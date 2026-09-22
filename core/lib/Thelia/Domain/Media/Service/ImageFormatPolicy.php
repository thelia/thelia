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
 * Answers the one question the whole multi-format rendering hangs off: which formats
 * does this shop offer for an image, and at which quality?
 *
 * The themes, the rendering module and the back office all ask here rather than reading
 * the settings themselves, so a shop only ever has one answer to give. The source format
 * is never in the answer: it is always produced and always the fallback.
 *
 * A format a merchant activated but the server cannot write is dropped here rather than
 * at rendering time — a missing variant is a browser offered one format less, never a
 * broken image and never an exception on a catalogue page.
 */
final readonly class ImageFormatPolicy
{
    public const FORMATS_CONFIG_NAME = 'image_formats';

    public function __construct(
        private ImageFormatCapabilities $capabilities = new ImageFormatCapabilities(),
    ) {
    }

    /**
     * The formats to offer on top of the source one, most efficient first.
     *
     * @return list<ImageFormat>
     */
    public function activeFormats(): array
    {
        return array_values(array_filter(
            ImageFormat::listFromStoredValue(ConfigQuery::read(self::FORMATS_CONFIG_NAME, '')),
            fn (ImageFormat $format): bool => $this->capabilities->supports($format)
        ));
    }

    /**
     * The formats a merchant asked for that this server cannot write.
     *
     * The back office shows them: a setting silently doing nothing is worse than a
     * setting saying why.
     *
     * @return list<ImageFormat>
     */
    public function unsupportedActiveFormats(): array
    {
        return array_values(array_filter(
            ImageFormat::listFromStoredValue(ConfigQuery::read(self::FORMATS_CONFIG_NAME, '')),
            fn (ImageFormat $format): bool => !$this->capabilities->supports($format)
        ));
    }

    /** Whether the shop offers anything beyond the source format at all. */
    public function offersModernFormats(): bool
    {
        return [] !== $this->activeFormats();
    }

    /**
     * The encoder quality for a format, on a 0-100 scale.
     *
     * Each format carries its own setting because the scales are not comparable: the
     * same number is not the same picture from one encoder to the next. A value outside
     * the scale is ignored rather than passed to the encoder.
     */
    public function qualityFor(ImageFormat $format): int
    {
        $stored = ConfigQuery::read($format->qualityConfigName(), null);

        if (null === $stored || '' === $stored || !is_numeric($stored)) {
            return $format->defaultQuality();
        }

        $quality = (int) $stored;

        return $quality >= 1 && $quality <= 100 ? $quality : $format->defaultQuality();
    }
}
