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

namespace Thelia\Tests\Integration\Domain\Media;

use Thelia\Domain\Media\Enum\ImageFormat;
use Thelia\Domain\Media\Service\ImageFormatCapabilities;
use Thelia\Domain\Media\Service\ImageFormatPolicy;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * Which formats a shop actually offers, once the setting has met the server.
 *
 * A merchant may activate a format this server cannot write — AVIF on a shared host
 * without a recent GD is the everyday case. The rule is that it costs them a format,
 * never a broken image: the format is dropped before anything tries to encode it, and
 * the back office can say which ones were dropped.
 */
final class ImageFormatPolicyTest extends IntegrationTestCase
{
    private ?string $previousFormats = null;

    private ?string $previousWebpQuality = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousFormats = ConfigQuery::read(ImageFormatPolicy::FORMATS_CONFIG_NAME);
        $this->previousWebpQuality = ConfigQuery::read(ImageFormat::Webp->qualityConfigName());
    }

    protected function tearDown(): void
    {
        ConfigQuery::write(ImageFormatPolicy::FORMATS_CONFIG_NAME, (string) $this->previousFormats);
        ConfigQuery::write(ImageFormat::Webp->qualityConfigName(), (string) $this->previousWebpQuality);

        parent::tearDown();
    }

    public function testAShopOffersNothingBeyondTheSourceFormatByDefault(): void
    {
        ConfigQuery::write(ImageFormatPolicy::FORMATS_CONFIG_NAME, '');

        $policy = new ImageFormatPolicy();

        self::assertSame([], $policy->activeFormats());
        self::assertFalse($policy->offersModernFormats());
    }

    public function testAnActivatedFormatIsOfferedWhenTheServerCanWriteIt(): void
    {
        $capabilities = new ImageFormatCapabilities();

        if (!$capabilities->supports(ImageFormat::Webp)) {
            self::markTestSkipped('This server cannot write WebP with the configured driver.');
        }

        ConfigQuery::write(ImageFormatPolicy::FORMATS_CONFIG_NAME, 'webp');

        $policy = new ImageFormatPolicy();

        self::assertSame([ImageFormat::Webp], $policy->activeFormats());
        self::assertTrue($policy->offersModernFormats());
        self::assertSame([], $policy->unsupportedActiveFormats());
    }

    /**
     * The whole point of asking the server first: a format it cannot write leaves the
     * active list and shows up as unsupported, instead of reaching an encoder that
     * would throw halfway through rendering a catalogue page.
     */
    public function testAFormatTheServerCannotWriteIsDroppedAndReported(): void
    {
        $capabilities = new ImageFormatCapabilities();
        $unsupported = array_values(array_filter(
            ImageFormat::cases(),
            static fn (ImageFormat $format): bool => !$capabilities->supports($format)
        ));

        if ([] === $unsupported) {
            self::markTestSkipped('This server writes every format Thelia knows.');
        }

        ConfigQuery::write(ImageFormatPolicy::FORMATS_CONFIG_NAME, ImageFormat::toStoredValue($unsupported));

        $policy = new ImageFormatPolicy();

        self::assertSame([], $policy->activeFormats());
        self::assertSame($unsupported, $policy->unsupportedActiveFormats());
    }

    public function testQualityFallsBackToTheFormatDefaultWhenUnsetOrOutOfScale(): void
    {
        $policy = new ImageFormatPolicy();

        ConfigQuery::write(ImageFormat::Webp->qualityConfigName(), '');
        self::assertSame(ImageFormat::Webp->defaultQuality(), $policy->qualityFor(ImageFormat::Webp));

        ConfigQuery::write(ImageFormat::Webp->qualityConfigName(), '0');
        self::assertSame(ImageFormat::Webp->defaultQuality(), $policy->qualityFor(ImageFormat::Webp));

        ConfigQuery::write(ImageFormat::Webp->qualityConfigName(), '140');
        self::assertSame(ImageFormat::Webp->defaultQuality(), $policy->qualityFor(ImageFormat::Webp));

        ConfigQuery::write(ImageFormat::Webp->qualityConfigName(), 'best');
        self::assertSame(ImageFormat::Webp->defaultQuality(), $policy->qualityFor(ImageFormat::Webp));
    }

    public function testQualityIsReadPerFormat(): void
    {
        ConfigQuery::write(ImageFormat::Webp->qualityConfigName(), '62');

        $policy = new ImageFormatPolicy();

        self::assertSame(62, $policy->qualityFor(ImageFormat::Webp));
        self::assertSame(ImageFormat::Avif->defaultQuality(), $policy->qualityFor(ImageFormat::Avif));
    }
}
