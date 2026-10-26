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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Domain\Localization\VisitorCountry\IpCountryLookupInterface;
use Thelia\Domain\Localization\VisitorCountry\IpDatabaseCountryResolver;
use Thelia\Domain\Localization\VisitorCountry\NullCountryResolver;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountrySettings;
use Thelia\Model\ConfigQuery;

final class IpDatabaseCountryResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
    }

    public function testItAsksTheLookupForTheClientAddressAndTheConfiguredFile(): void
    {
        ConfigQuery::initCache(['visitor_country_source' => 'ip_database', 'visitor_country_database' => 'GeoLite2-Country.mmdb']);
        $lookup = $this->lookup('NL');

        $country = (new IpDatabaseCountryResolver(new VisitorCountrySettings(), $lookup))
            ->resolve(Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7']));

        self::assertSame('NL', $country);
        self::assertSame([['203.0.113.7', 'GeoLite2-Country.mmdb']], $lookup->calls);
    }

    public function testItStaysSilentWhenTheSourceIsTheHeader(): void
    {
        ConfigQuery::initCache(['visitor_country_source' => 'header']);
        $lookup = $this->lookup('NL');

        $country = (new IpDatabaseCountryResolver(new VisitorCountrySettings(), $lookup))
            ->resolve(Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7']));

        self::assertNull($country);
        self::assertSame([], $lookup->calls);
    }

    public function testTheLastResolverOfTheChainHasNothingToSay(): void
    {
        self::assertNull((new NullCountryResolver())->resolve(Request::create('/')));
    }

    private function lookup(?string $answer): IpCountryLookupInterface
    {
        return new class($answer) implements IpCountryLookupInterface {
            /** @var list<array{string, string}> */
            public array $calls = [];

            public function __construct(private ?string $answer)
            {
            }

            public function lookup(string $ipAddress, string $databaseFileName): ?string
            {
                $this->calls[] = [$ipAddress, $databaseFileName];

                return $this->answer;
            }
        };
    }
}
