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

namespace Thelia\Domain\Media\Enum;

/**
 * An output format a shop may offer for its catalogue images.
 *
 * The source format is not listed here: it is always produced and always the last
 * candidate a browser is offered, so a shop that recognises none of these formats still
 * gets its images. What this enum names is the modern formats a shop may add on top.
 *
 * The order of the cases is the order a browser is offered them, most efficient first.
 */
enum ImageFormat: string
{
    /** Smallest of the three at equal visual quality, and the least widely supported. */
    case Avif = 'avif';

    /** Read by every current browser, and by every browser released since 2020. */
    case Webp = 'webp';

    /**
     * The formats a stored setting names, in the order a browser is offered them.
     *
     * The stored value is a comma-separated list because a shop chooses several: an
     * unknown or empty entry is dropped rather than refused, so a setting written by a
     * newer Thelia — or by a merchant's own hand — degrades to the formats this version
     * knows instead of taking the catalogue down.
     *
     * @return list<self>
     */
    public static function listFromStoredValue(?string $value): array
    {
        $named = array_filter(array_map(
            static fn (string $entry): ?self => self::tryFrom(strtolower(trim($entry))),
            explode(',', (string) $value)
        ));

        return array_values(array_filter(self::cases(), static fn (self $format): bool => \in_array($format, $named, true)));
    }

    /**
     * The value stored for a list of formats, in the enum's own order.
     *
     * @param list<self> $formats
     */
    public static function toStoredValue(array $formats): string
    {
        return implode(',', array_map(
            static fn (self $format): string => $format->value,
            array_values(array_filter(self::cases(), static fn (self $format): bool => \in_array($format, $formats, true)))
        ));
    }

    /** The media type a <source type> announces for this format. */
    public function mimeType(): string
    {
        return 'image/'.$this->value;
    }

    /**
     * The extension a variant file carries.
     *
     * It is what the web server reads to pick the Content-Type it sends, so a variant
     * whose extension lies about its bytes is served as the wrong media type and the
     * browser refuses it.
     */
    public function extension(): string
    {
        return $this->value;
    }

    /**
     * The encoder quality this format defaults to, on a 0-100 scale.
     *
     * The numbers differ because the scales differ: quality 82 of a JPEG and quality 82
     * of an AVIF are not the same picture and not the same weight. These come from a
     * measurement on the demo catalogue, not from intuition — see the CHANGELOG entry
     * for the figures.
     */
    public function defaultQuality(): int
    {
        return match ($this) {
            self::Avif => 50,
            self::Webp => 75,
        };
    }

    /** The shop setting holding this format's quality. */
    public function qualityConfigName(): string
    {
        return 'image_quality_'.$this->value;
    }
}
