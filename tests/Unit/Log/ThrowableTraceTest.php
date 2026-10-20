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

namespace Thelia\Tests\Unit\Log;

use PHPUnit\Framework\TestCase;
use Thelia\Log\ThrowableTrace;

final class ThrowableTraceTest extends TestCase
{
    public function testTheTraceLeavesTheArgumentsOfItsFramesOut(): void
    {
        $described = ThrowableTrace::describe($this->thrownWith('a-connection-password'));

        self::assertStringNotContainsString('a-connection-password', $described);
        self::assertStringContainsString(__CLASS__.'->thrownWith()', $described);
    }

    public function testTheMessageStaysOnOneLineAndTheCauseFollows(): void
    {
        $cause = new \PDOException("Table 'delivery_closure' is masked\nforged line");
        $described = ThrowableTrace::describe(new \RuntimeException('Could not count', 0, $cause));

        self::assertStringStartsWith('RuntimeException: Could not count', $described);
        self::assertStringContainsString("Caused by PDOException: Table 'delivery_closure' is masked", $described);
        self::assertStringNotContainsString("\nforged line", $described);
    }

    public function testAtMostTenCausesAreWritten(): void
    {
        $throwable = new \RuntimeException('cause 0');

        for ($level = 1; $level < 25; ++$level) {
            $throwable = new \RuntimeException('cause '.$level, 0, $throwable);
        }

        $described = ThrowableTrace::describe($throwable);

        self::assertSame(9, substr_count($described, 'Caused by '));
        self::assertStringEndsWith('... 15 more causes', $described);
    }

    public function testASingleUnwrittenCauseIsNamedInTheSingular(): void
    {
        $throwable = new \RuntimeException('cause 0');

        for ($level = 1; $level <= 10; ++$level) {
            $throwable = new \RuntimeException('cause '.$level, 0, $throwable);
        }

        self::assertStringEndsWith('... 1 more cause', ThrowableTrace::describe($throwable));
    }

    public function testTheNameOfAnAnonymousClassStaysOnItsLine(): void
    {
        $described = ThrowableTrace::describe(new class('anonymous') extends \RuntimeException {});

        self::assertStringNotContainsString("\0", $described);
    }

    private function thrownWith(string $password): \Throwable
    {
        // The arguments are captured, as on a server where zend.exception_ignore_args is off.
        $ignoreArguments = ini_set('zend.exception_ignore_args', '0');

        try {
            $this->throwFor($password);
        } catch (\Throwable $throwable) {
            return $throwable;
        } finally {
            ini_set('zend.exception_ignore_args', (string) $ignoreArguments);
        }

        self::fail('Nothing was thrown.');
    }

    private function throwFor(string $password): never
    {
        throw new \RuntimeException('Connection refused');
    }
}
