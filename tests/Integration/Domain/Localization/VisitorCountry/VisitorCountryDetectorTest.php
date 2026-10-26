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

namespace Thelia\Tests\Integration\Domain\Localization\VisitorCountry;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountryDetector;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountryResolverInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

final class VisitorCountryDetectorTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheHeaderCountryIsDetected(): void
    {
        self::assertSame('BE', $this->detectFromHeader('BE'));
    }

    public function testALowerCaseCodeIsNormalized(): void
    {
        self::assertSame('BE', $this->detectFromHeader('be'));
    }

    #[DataProvider('forgedValues')]
    public function testAForgedOrUnknownValueIsIgnoredWithoutAnError(string $value): void
    {
        self::assertNull($this->detectFromHeader($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forgedValues(): iterable
    {
        yield 'markup' => ["B<'"];
        yield 'alpha-3' => ['BEL'];
        yield 'unknown code' => ['XX'];
        yield 'Tor exit nodes' => ['T1'];
        yield 'empty' => [''];
    }

    public function testAKnownCountryThatIsNotVisibleIsIgnored(): void
    {
        $this->createFixtureFactory()->country(['isocode' => '901', 'isoalpha2' => 'YH', 'isoalpha3' => 'YHH', 'visible' => 0]);

        self::assertNull($this->detectFromHeader('YH'));
    }

    public function testTheSourceNotSelectedIsNotAsked(): void
    {
        ConfigQuery::write('visitor_country_source', 'ip_database');
        ConfigQuery::resetCache();

        self::assertNull($this->detectFromHeader('BE'));
    }

    public function testTheConfiguredHeaderIsTheOneRead(): void
    {
        ConfigQuery::write('visitor_country_header', 'X-Geo');
        ConfigQuery::resetCache();
        $request = Request::create('/');
        $request->headers->set('CF-IPCountry', 'FR');
        $request->headers->set('X-Geo', 'BE');

        self::assertSame('BE', $this->getService(VisitorCountryDetector::class)->detect($request)?->getIsoalpha2());
    }

    public function testTheFirstResolverThatAnswersWinsAndTheOthersAreNotAsked(): void
    {
        $calls = [];
        $detector = new VisitorCountryDetector([
            $this->resolver(null, $calls, 'silent'),
            $this->resolver('NL', $calls, 'first'),
            $this->resolver('BE', $calls, 'never asked'),
        ]);

        self::assertSame('NL', $detector->detect(Request::create('/'))?->getIsoalpha2());
        self::assertSame(['silent', 'first'], $calls);
    }

    public function testAResolverThatAnswersAnUnusableCodeFallsThroughToTheNext(): void
    {
        $calls = [];
        $detector = new VisitorCountryDetector([
            $this->resolver('XX', $calls, 'forged'),
            $this->resolver('b1', $calls, 'malformed'),
            $this->resolver('be', $calls, 'valid'),
        ]);

        self::assertSame('BE', $detector->detect(Request::create('/'))?->getIsoalpha2());
        self::assertSame(['forged', 'malformed', 'valid'], $calls);
    }

    public function testAChainThatFindsNothingFindsNothing(): void
    {
        $calls = [];

        self::assertNull((new VisitorCountryDetector([$this->resolver(null, $calls, 'a')]))->detect(Request::create('/')));
    }

    private function detectFromHeader(string $value): ?string
    {
        $request = Request::create('/');
        $request->headers->set('CF-IPCountry', $value);

        return $this->getService(VisitorCountryDetector::class)->detect($request)?->getIsoalpha2();
    }

    /**
     * @param list<string> $calls
     */
    private function resolver(?string $answer, array &$calls, string $name): VisitorCountryResolverInterface
    {
        return new class($answer, $calls, $name) implements VisitorCountryResolverInterface {
            /**
             * @param list<string> $calls
             */
            public function __construct(private ?string $answer, private array &$calls, private string $name)
            {
            }

            public function resolve(Request $request): ?string
            {
                $this->calls[] = $this->name;

                return $this->answer;
            }
        };
    }
}
