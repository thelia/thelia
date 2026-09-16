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
 * The address a merchant pasted is not one of the platforms this shop accepts.
 *
 * It carries the platforms that were enabled when it was raised, so the caller
 * can name them to the merchant instead of leaving him guessing.
 */
final class UnsupportedVideoUrlException extends \InvalidArgumentException
{
    /**
     * @param list<VideoProvider> $enabledProviders
     */
    public function __construct(
        string $message,
        private readonly array $enabledProviders,
    ) {
        parent::__construct($message);
    }

    /**
     * @return list<VideoProvider>
     */
    public function getEnabledProviders(): array
    {
        return $this->enabledProviders;
    }

    /**
     * The platforms of this shop, written the way a merchant knows them.
     */
    public function getEnabledProviderLabels(): string
    {
        return implode(', ', array_map(
            static fn (VideoProvider $provider): string => $provider->label(),
            $this->enabledProviders,
        ));
    }
}
