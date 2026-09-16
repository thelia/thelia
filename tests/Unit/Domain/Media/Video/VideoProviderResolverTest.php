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

namespace Thelia\Tests\Unit\Domain\Media\Video;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Media\Video\UnsupportedVideoUrlException;
use Thelia\Domain\Media\Video\VideoProvider;
use Thelia\Domain\Media\Video\VideoProviderResolver;

/**
 * Every test hands the resolver its own platform list, so nothing here reaches
 * the shop configuration and the cases a shop cannot produce (a single platform
 * enabled, all of them) are testable.
 */
final class VideoProviderResolverTest extends TestCase
{
    private const ALL_PROVIDERS = 'youtube,vimeo,dailymotion';

    public static function recognisedAddresses(): \Generator
    {
        yield 'youtube watch with extra parameters' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s', VideoProvider::Youtube, 'dQw4w9WgXcQ'];
        yield 'youtube short link' => ['https://youtu.be/dQw4w9WgXcQ', VideoProvider::Youtube, 'dQw4w9WgXcQ'];
        yield 'youtube shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', VideoProvider::Youtube, 'dQw4w9WgXcQ'];
        yield 'youtube embed without www' => ['https://youtube.com/embed/dQw4w9WgXcQ', VideoProvider::Youtube, 'dQw4w9WgXcQ'];
        yield 'vimeo page' => ['https://vimeo.com/76979871', VideoProvider::Vimeo, '76979871'];
        yield 'vimeo player' => ['https://player.vimeo.com/video/76979871', VideoProvider::Vimeo, '76979871'];
        yield 'dailymotion page' => ['https://www.dailymotion.com/video/x8abcde', VideoProvider::Dailymotion, 'x8abcde'];
        yield 'dailymotion short link' => ['https://dai.ly/x8abcde', VideoProvider::Dailymotion, 'x8abcde'];
    }

    #[DataProvider('recognisedAddresses')]
    public function testARecognisedAddressKeepsOnlyItsPlatformAndIdentifier(string $url, VideoProvider $provider, string $externalId): void
    {
        $resolved = $this->resolver()->resolve($url);

        self::assertSame($provider, $resolved->provider);
        self::assertSame($externalId, $resolved->externalId);
    }

    public static function refusedAddresses(): \Generator
    {
        yield 'unknown domain' => ['https://videos.example.com/watch?v=dQw4w9WgXcQ'];
        yield 'javascript uri' => ['javascript:alert(1)'];
        yield 'data uri' => ['data:text/html;base64,PHNjcmlwdD4='];
        yield 'identifier with a slash' => ['https://youtu.be/dQw4w9WgXcQ/../../etc'];
        yield 'identifier with a quote' => ['https://www.dailymotion.com/video/x8ab"cde'];
        yield 'identifier too long' => ['https://vimeo.com/'.str_repeat('1', 65)];
        yield 'youtube page carrying no video' => ['https://www.youtube.com/watch'];
        yield 'nothing at all' => [''];
    }

    #[DataProvider('refusedAddresses')]
    public function testARefusedAddressNamesTheEnabledPlatforms(string $url): void
    {
        $this->expectException(UnsupportedVideoUrlException::class);
        $this->expectExceptionMessage('This address is not recognised. Accepted platforms: YouTube, Vimeo, Dailymotion.');

        $this->resolver()->resolve($url);
    }

    public function testAPlatformTheShopTurnedOffIsRefusedLikeAnUnknownOne(): void
    {
        $resolver = $this->resolver('youtube,vimeo');

        try {
            $resolver->resolve('https://www.dailymotion.com/video/x8abcde');
            self::fail('A platform left out of the shop configuration must not resolve.');
        } catch (UnsupportedVideoUrlException $exception) {
            self::assertSame(
                'This address is not recognised. Accepted platforms: YouTube, Vimeo.',
                $exception->getMessage(),
            );
            self::assertSame(
                [VideoProvider::Youtube, VideoProvider::Vimeo],
                $exception->getEnabledProviders(),
            );
        }
    }

    public function testEnabledProvidersIgnoresUnknownCodesAndNeverEnablesHostedFiles(): void
    {
        $resolver = $this->resolver('youtube, FILE ,tiktok,,vimeo,youtube');

        self::assertSame([VideoProvider::Youtube, VideoProvider::Vimeo], $resolver->enabledProviders());
    }

    /**
     * The fallback is for a shop that has never been asked which platforms it
     * offers. A merchant who unticks all of them has answered, and his answer is
     * an empty list — not a silent return to every platform the core ships with.
     */
    public function testAShopThatTurnedEveryPlatformOffOffersNone(): void
    {
        foreach (['', '   ', ' , , '] as $configured) {
            self::assertSame([], $this->resolver($configured)->enabledProviders());
        }

        $this->expectException(UnsupportedVideoUrlException::class);
        $this->expectExceptionMessage('No video platform is enabled on this shop.');

        $this->resolver('')->resolve('https://youtu.be/dQw4w9WgXcQ');
    }

    public function testTheShippedDefaultOffersEveryPlatformButHostedFiles(): void
    {
        self::assertSame(
            [VideoProvider::Youtube, VideoProvider::Vimeo, VideoProvider::Dailymotion],
            $this->resolver(VideoProviderResolver::DEFAULT_PROVIDERS)->enabledProviders(),
        );
    }

    public function testEmbedAddressesAreBuiltBackFromTheIdentifier(): void
    {
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', VideoProvider::Youtube->embedUrl('dQw4w9WgXcQ'));
        self::assertSame('https://player.vimeo.com/video/76979871', VideoProvider::Vimeo->embedUrl('76979871'));
        self::assertSame('https://www.dailymotion.com/embed/video/x8abcde', VideoProvider::Dailymotion->embedUrl('x8abcde'));
        self::assertNull(VideoProvider::File->embedUrl('anything'));
    }

    public function testFrameSourcesAreTheOriginsOfTheEmbedAddresses(): void
    {
        self::assertSame('https://www.youtube-nocookie.com', VideoProvider::Youtube->frameSource());
        self::assertSame('https://player.vimeo.com', VideoProvider::Vimeo->frameSource());
        self::assertSame('https://www.dailymotion.com', VideoProvider::Dailymotion->frameSource());
        self::assertNull(VideoProvider::File->frameSource());
    }

    private function resolver(string $providers = self::ALL_PROVIDERS): VideoProviderResolver
    {
        return new VideoProviderResolver($providers);
    }
}
