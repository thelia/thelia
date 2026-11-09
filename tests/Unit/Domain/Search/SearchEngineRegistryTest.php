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

namespace Thelia\Tests\Unit\Domain\Search;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Thelia\Domain\Search\SearchEngineRegistry;
use Thelia\Tests\Support\Search\InMemorySearchEngine;

final class SearchEngineRegistryTest extends TestCase
{
    public function testTellingAnEngineIsInstalledBuildsNone(): void
    {
        $built = 0;
        $registry = new SearchEngineRegistry(new RewindableGenerator(static function () use (&$built): \Generator {
            ++$built;

            yield new InMemorySearchEngine();
        }, 1));

        self::assertTrue($registry->hasEngine());
        self::assertSame(0, $built);
    }

    public function testTheFirstEngineAnswersAndIsBuiltOnce(): void
    {
        $built = 0;
        $first = new InMemorySearchEngine();
        $registry = new SearchEngineRegistry(new RewindableGenerator(static function () use (&$built, $first): \Generator {
            ++$built;

            yield $first;
            yield new InMemorySearchEngine();
        }, 2));

        self::assertSame($first, $registry->engine());
        self::assertSame($first, $registry->engine());
        self::assertSame(1, $built);
    }

    public function testASecondEngineInstalledIsToldOnce(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $levels = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->levels[] = (string) $level;
            }
        };
        $registry = new SearchEngineRegistry([new InMemorySearchEngine(), new InMemorySearchEngine()], $logger);

        $registry->engine();
        $registry->engine();

        self::assertSame(['warning'], $logger->levels);
    }

    public function testASingleEngineIsNotWarnedAbout(): void
    {
        $logger = new class extends AbstractLogger {
            public int $calls = 0;

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                ++$this->calls;
            }
        };

        (new SearchEngineRegistry([new InMemorySearchEngine()], $logger))->engine();

        self::assertSame(0, $logger->calls);
    }

    public function testWithoutAnEngineThereIsNone(): void
    {
        $registry = new SearchEngineRegistry(new RewindableGenerator(static fn (): \Generator => yield from [], 0));

        self::assertFalse($registry->hasEngine());
        self::assertNull($registry->engine());
    }
}
