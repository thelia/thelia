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
 * What the shop keeps of the address a merchant pasted: the platform, and the
 * identifier of the video on it.
 */
final readonly class ResolvedVideo
{
    public function __construct(
        public VideoProvider $provider,
        public string $externalId,
    ) {
    }
}
