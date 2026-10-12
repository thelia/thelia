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
use Psr\Log\LogLevel;
use Thelia\Log\Destination\TlogDestinationText;
use Thelia\Log\Tlog;

/**
 * Tlog writes a message only when its level reaches the configured threshold,
 * and each argument given to an add*() shortcut becomes a line of its own.
 */
final class TlogTest extends TestCase
{
    public function testAMessageAtTheThresholdIsWritten(): void
    {
        $this->expectOutputString("WARNING: Stock is running low\n");

        $this->loggerAt(Tlog::WARNING)->warning('Stock is running low');
    }

    public function testAMessageAboveTheThresholdIsWritten(): void
    {
        $this->expectOutputString("ERROR: Payment refused\n");

        $this->loggerAt(Tlog::WARNING)->error('Payment refused');
    }

    public function testAMessageBelowTheThresholdIsDropped(): void
    {
        $this->expectOutputString('');

        $this->loggerAt(Tlog::WARNING)->info('Cart updated');
    }

    public function testALevelOutsideTheScaleIsDropped(): void
    {
        $this->expectOutputString('');

        $this->loggerAt(Tlog::DEBUG)->log(450, 'Between warning and error');
    }

    public function testEveryArgumentOfAShortcutBecomesALine(): void
    {
        $this->expectOutputString("WARNING: First\nWARNING: Second\n");

        $this->loggerAt(Tlog::DEBUG)->addWarning('First', 'Second');
    }

    /**
     * Tlog is a PSR-3 logger: a caller that only knows the interface names the level
     * with a LogLevel string.
     */
    public function testAPsrLevelNameIsUnderstood(): void
    {
        $this->expectOutputString("WARNING: Stock is running low\nERROR: Payment refused\n");

        $logger = $this->loggerAt(Tlog::DEBUG);
        $logger->log(LogLevel::WARNING, 'Stock is running low');
        $logger->log(LogLevel::ERROR, 'Payment refused');
    }

    /**
     * Tlog::getInstance() reads its configuration from the database, so the
     * logger is built bare and configured through its public setters. The
     * singleton is left untouched.
     */
    private function loggerAt(int $level): Tlog
    {
        $logger = (new \ReflectionClass(Tlog::class))->newInstanceWithoutConstructor();

        return $logger
            ->setLevel($level)
            ->setFiles('*')
            ->setPrefix('#LEVEL: ')
            ->setDestinations(TlogDestinationText::class);
    }
}
