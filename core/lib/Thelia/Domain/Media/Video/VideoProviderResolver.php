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

use Thelia\Model\ConfigQuery;

/**
 * Turns the address a merchant pasted into the only two things the shop stores:
 * the platform, and the identifier of the video on it.
 *
 * Nothing else of the address survives. The front office rebuilds the frame
 * address from VideoProvider::embedUrl(), so a merchant with catalogue rights
 * cannot push an arbitrary origin, a javascript: uri or extra player parameters
 * into the page through the address field.
 *
 * The list of platforms a shop offers is the `video_providers` configuration
 * variable; an address pointing at a platform the merchant turned off is refused
 * like an unknown one. `file` is never part of that list: a hosted video is
 * uploaded, not pasted.
 */
final readonly class VideoProviderResolver
{
    public const PROVIDERS_VARIABLE = 'video_providers';

    public const DEFAULT_PROVIDERS = 'youtube,vimeo,dailymotion';

    /**
     * An identifier is what the platforms use, and what a path segment may safely
     * carry: letters, digits, dash and underscore.
     */
    private const EXTERNAL_ID_PATTERN = '#^[A-Za-z0-9_-]{1,64}$#';

    /**
     * The hosts each platform answers on, without their `www.` prefix.
     */
    private const PROVIDER_HOSTS = [
        'youtube.com' => VideoProvider::Youtube,
        'm.youtube.com' => VideoProvider::Youtube,
        'youtube-nocookie.com' => VideoProvider::Youtube,
        'youtu.be' => VideoProvider::Youtube,
        'vimeo.com' => VideoProvider::Vimeo,
        'player.vimeo.com' => VideoProvider::Vimeo,
        'dailymotion.com' => VideoProvider::Dailymotion,
        'dai.ly' => VideoProvider::Dailymotion,
    ];

    /**
     * @param string|null $configuredProviders the platform list to apply, for a caller
     *                                         holding its own; null reads the shop one
     */
    public function __construct(
        private ?string $configuredProviders = null,
    ) {
    }

    /**
     * @return list<VideoProvider>
     */
    public function enabledProviders(): array
    {
        // The default applies to a shop that has never been asked the question, and
        // to that shop only. A merchant who unticks every platform has answered it:
        // turning his empty list back into the full one would re-enable, behind his
        // back, exactly what he took off the shop.
        $raw = $this->configuredProviders
            ?? ConfigQuery::read(self::PROVIDERS_VARIABLE)
            ?? self::DEFAULT_PROVIDERS;

        $providers = [];

        foreach (explode(',', $raw) as $code) {
            $provider = VideoProvider::tryFrom(strtolower(trim($code)));

            // A shop enables platforms; a hosted video is uploaded, never pasted.
            if (null === $provider || VideoProvider::File === $provider) {
                continue;
            }

            if (!\in_array($provider, $providers, true)) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * @throws UnsupportedVideoUrlException when the address is not one of the platforms this shop accepts
     */
    public function resolve(string $url): ResolvedVideo
    {
        $enabledProviders = $this->enabledProviders();
        $parts = parse_url(trim($url));

        if (false === $parts || !isset($parts['host'], $parts['scheme'])) {
            throw $this->refuse($enabledProviders);
        }

        if (!\in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw $this->refuse($enabledProviders);
        }

        $host = preg_replace('#^www\.#', '', strtolower($parts['host']));
        $provider = self::PROVIDER_HOSTS[$host] ?? null;

        if (null === $provider || !\in_array($provider, $enabledProviders, true)) {
            throw $this->refuse($enabledProviders);
        }

        $segments = array_values(array_filter(explode('/', $parts['path'] ?? ''), static fn (string $segment): bool => '' !== $segment));
        parse_str($parts['query'] ?? '', $query);

        $externalId = match ($provider) {
            VideoProvider::Youtube => $this->youtubeId($host, $segments, $query),
            VideoProvider::Vimeo => $this->vimeoId($segments),
            VideoProvider::Dailymotion => $this->dailymotionId($host, $segments),
            VideoProvider::File => null,
        };

        if (null === $externalId || 1 !== preg_match(self::EXTERNAL_ID_PATTERN, $externalId)) {
            throw $this->refuse($enabledProviders);
        }

        return new ResolvedVideo($provider, $externalId);
    }

    /**
     * @param list<string>         $segments
     * @param array<string, mixed> $query
     */
    private function youtubeId(string $host, array $segments, array $query): ?string
    {
        if ('youtu.be' === $host) {
            return 1 === \count($segments) ? $segments[0] : null;
        }

        if (['watch'] === $segments) {
            return \is_string($query['v'] ?? null) ? $query['v'] : null;
        }

        if (2 === \count($segments) && \in_array($segments[0], ['shorts', 'embed', 'v', 'live'], true)) {
            return $segments[1];
        }

        return null;
    }

    /**
     * @param list<string> $segments
     */
    private function vimeoId(array $segments): ?string
    {
        if (2 === \count($segments) && 'video' === $segments[0]) {
            return $segments[1];
        }

        return 1 === \count($segments) ? $segments[0] : null;
    }

    /**
     * @param list<string> $segments
     */
    private function dailymotionId(string $host, array $segments): ?string
    {
        if ('dai.ly' === $host) {
            return 1 === \count($segments) ? $segments[0] : null;
        }

        if (3 === \count($segments) && 'embed' === $segments[0] && 'video' === $segments[1]) {
            return $segments[2];
        }

        if (2 === \count($segments) && 'video' === $segments[0]) {
            return $segments[1];
        }

        return null;
    }

    /**
     * @param list<VideoProvider> $enabledProviders
     */
    private function refuse(array $enabledProviders): UnsupportedVideoUrlException
    {
        if ([] === $enabledProviders) {
            return new UnsupportedVideoUrlException(
                'No video platform is enabled on this shop.',
                $enabledProviders,
            );
        }

        $labels = implode(', ', array_map(
            static fn (VideoProvider $provider): string => $provider->label(),
            $enabledProviders,
        ));

        return new UnsupportedVideoUrlException(
            \sprintf('This address is not recognised. Accepted platforms: %s.', $labels),
            $enabledProviders,
        );
    }
}
