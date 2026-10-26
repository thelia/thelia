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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Thelia\Domain\Localization\VisitorCountry\MaxMindDatabaseLookup;

final class MaxMindDatabaseLookupTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/thelia-geoip-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    #[DataProvider('refusedFileNames')]
    public function testAFileNameThatLeavesTheDirectoryIsRefusedEvenWhenTheFileExists(string $name): void
    {
        // A readable file the name would reach if it were followed.
        file_put_contents($this->directory.'/x.mmdb', 'not a database');
        $logger = $this->logger();

        $lookup = new MaxMindDatabaseLookup($logger, $this->directory.'/inner');

        self::assertNull($lookup->lookup('203.0.113.7', $name));
        self::assertCount(1, $logger->warnings);
        self::assertStringContainsString('geoip directory', $logger->warnings[0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedFileNames(): iterable
    {
        yield 'parent directory' => ['../x.mmdb'];
        yield 'subdirectory' => ['a/b.mmdb'];
        yield 'absolute path' => ['/etc/passwd'];
        yield 'backslash' => ['a\\b.mmdb'];
        yield 'double dot inside a name' => ['x..mmdb'];
        yield 'null byte' => ["x.mmdb\0.txt"];
        yield 'empty' => [''];
    }

    public function testAMissingDatabaseIsOneWarningWithoutTheAddress(): void
    {
        $logger = $this->logger();

        $result = (new MaxMindDatabaseLookup($logger, $this->directory))->lookup('203.0.113.7', 'GeoLite2-Country.mmdb');

        self::assertNull($result);
        self::assertCount(1, $logger->warnings);
        self::assertStringNotContainsString('203.0.113.7', json_encode($logger->records, \JSON_THROW_ON_ERROR));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testACorruptedDatabaseIsOneWarningWithoutTheAddress(): void
    {
        $this->declareReader('throw new \\InvalidArgumentException("The value \\"203.0.113.7\\" is not valid");');
        file_put_contents($this->directory.'/broken.mmdb', 'definitely not a MaxMind database');
        $logger = $this->logger();

        $result = (new MaxMindDatabaseLookup($logger, $this->directory))->lookup('203.0.113.7', 'broken.mmdb');

        self::assertNull($result);
        self::assertCount(1, $logger->warnings);
        self::assertStringNotContainsString('203.0.113.7', json_encode($logger->records, \JSON_THROW_ON_ERROR));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTheCountryIsTheIsoCodeOfTheCountryRecord(): void
    {
        $this->declareReader('return ["country" => ["iso_code" => "NL"], "continent" => ["code" => "EU"]];');
        file_put_contents($this->directory.'/GeoLite2-Country.mmdb', 'x');

        $result = (new MaxMindDatabaseLookup($this->logger(), $this->directory))->lookup('203.0.113.7', 'GeoLite2-Country.mmdb');

        self::assertSame('NL', $result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAnAddressTheDatabaseDoesNotKnowIsNothingToSayAndNoWarning(): void
    {
        $this->declareReader('return null;');
        file_put_contents($this->directory.'/GeoLite2-Country.mmdb', 'x');
        $logger = $this->logger();

        $result = (new MaxMindDatabaseLookup($logger, $this->directory))->lookup('10.0.0.1', 'GeoLite2-Country.mmdb');

        self::assertNull($result);
        self::assertSame([], $logger->warnings);
    }

    public function testWithoutTheLibraryTheDetectionAnswersNothingAndSaysWhy(): void
    {
        if (class_exists(MaxMindDatabaseLookup::READER_CLASS)) {
            self::markTestSkipped('The maxmind-db/reader library is installed.');
        }

        file_put_contents($this->directory.'/GeoLite2-Country.mmdb', 'x');
        $logger = $this->logger();

        $result = (new MaxMindDatabaseLookup($logger, $this->directory))->lookup('203.0.113.7', 'GeoLite2-Country.mmdb');

        self::assertNull($result);
        self::assertCount(1, $logger->warnings);
        self::assertStringContainsString('maxmind-db/reader', $logger->warnings[0]);
    }

    /**
     * A stand-in for the optional library, declared in a separate process: once a class of
     * that name exists, the process can no longer play a shop that does not have it.
     */
    private function declareReader(string $getBody): void
    {
        eval('namespace MaxMind\\Db; final class Reader { public function __construct(string $file) {} public function get(string $ip): mixed { '.$getBody.' } public function close(): void {} }');
    }

    private function logger(): object
    {
        return new class extends AbstractLogger {
            /** @var list<string> */
            public array $warnings = [];
            /** @var list<array{string, array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $message, $context];

                if ('warning' === $level) {
                    $this->warnings[] = (string) $message;
                }
            }
        };
    }
}
