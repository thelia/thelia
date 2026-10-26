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

namespace Thelia\Tests\Unit\Domain\Localization\VisitorCountry;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Domain\Localization\VisitorCountry\HeaderCountryResolver;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountrySettings;
use Thelia\Model\ConfigQuery;

final class HeaderCountryResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
    }

    public function testItReadsTheConfiguredHeaderAsItWasSent(): void
    {
        $this->configure(['visitor_country_header' => 'X-Visitor-Country']);

        self::assertSame('be', $this->resolve(['X-Visitor-Country' => 'be']));
    }

    public function testTheDefaultHeaderIsCfIpCountry(): void
    {
        $this->configure([]);

        self::assertSame('BE', $this->resolve(['CF-IPCountry' => 'BE']));
    }

    public function testAnAbsentHeaderIsNothingToSay(): void
    {
        $this->configure([]);

        self::assertNull($this->resolve([]));
    }

    #[DataProvider('sourcesThatAreNotTheHeader')]
    public function testItStaysSilentWhenAnotherSourceIsSelected(string $source): void
    {
        $this->configure(['visitor_country_source' => $source]);

        self::assertNull($this->resolve(['CF-IPCountry' => 'BE']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sourcesThatAreNotTheHeader(): iterable
    {
        yield 'ip database' => ['ip_database'];
    }

    public function testAnUnknownSourceFallsBackToTheHeader(): void
    {
        $this->configure(['visitor_country_source' => 'carrier_pigeon']);

        self::assertSame('BE', $this->resolve(['CF-IPCountry' => 'BE']));
    }

    /**
     * @param array<string, string> $configuration
     */
    private function configure(array $configuration): void
    {
        ConfigQuery::initCache($configuration);
    }

    /**
     * @param array<string, string> $headers
     */
    private function resolve(array $headers): ?string
    {
        $request = Request::create('/');
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return (new HeaderCountryResolver(new VisitorCountrySettings()))->resolve($request);
    }
}
