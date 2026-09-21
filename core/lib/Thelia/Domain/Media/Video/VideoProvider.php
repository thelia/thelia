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

namespace Thelia\Domain\Media\Video;

/**
 * Where a product video is played from.
 *
 * The shop keeps the identifier of the video on its platform and nothing else of
 * the address a merchant pasted. Every address the front office renders is built
 * back from that identifier here, so a crafted address can never reach a player
 * attribute or a frame source.
 */
enum VideoProvider: string
{
    case Youtube = 'youtube';
    case Vimeo = 'vimeo';
    case Dailymotion = 'dailymotion';
    case File = 'file';

    /**
     * Whether this identifier has the shape the platform gives its videos.
     *
     * The shape is what tells an address read wrong from an address read right:
     * without it a mistyped or truncated identifier is stored as it is, and the
     * shopper is handed a player that answers nothing - the platform is the only
     * one that can say the video exists, but it cannot say it about an identifier
     * it would never have issued.
     */
    public function hasIdentifierShape(string $externalId): bool
    {
        $pattern = match ($this) {
            // 11 characters, the length YouTube has always issued.
            self::Youtube => '#^[A-Za-z0-9_-]{11}$#',
            // A number, as many digits as the platform has published videos.
            self::Vimeo => '#^[0-9]{1,12}$#',
            // A letter and a handful of characters after it: x97z2zc, k1ABCdef.
            self::Dailymotion => '#^[a-zA-Z][a-zA-Z0-9]{5,31}$#',
            // Nothing is issued for a video the shop hosts: it has a file, not an id.
            self::File => null,
        };

        return null !== $pattern && 1 === preg_match($pattern, $externalId);
    }

    /**
     * The address of the player frame, null for a video the shop hosts itself.
     */
    public function embedUrl(string $externalId): ?string
    {
        return match ($this) {
            self::Youtube => 'https://www.youtube-nocookie.com/embed/'.$externalId,
            self::Vimeo => 'https://player.vimeo.com/video/'.$externalId,
            self::Dailymotion => 'https://www.dailymotion.com/embed/video/'.$externalId,
            self::File => null,
        };
    }

    /**
     * The origin a content security policy has to allow in frame-src for this
     * player to load, null for a video the shop hosts itself.
     */
    public function frameSource(): ?string
    {
        return match ($this) {
            self::Youtube => 'https://www.youtube-nocookie.com',
            self::Vimeo => 'https://player.vimeo.com',
            self::Dailymotion => 'https://www.dailymotion.com',
            self::File => null,
        };
    }

    /**
     * The name of the platform as it is written to a merchant.
     */
    public function label(): string
    {
        return match ($this) {
            self::Youtube => 'YouTube',
            self::Vimeo => 'Vimeo',
            self::Dailymotion => 'Dailymotion',
            self::File => 'File',
        };
    }
}
