<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Log;

use PHPUnit\Framework\TestCase;
use Thelia\Log\Tlog;

class TlogTest extends TestCase
{
    protected static $logger;

    protected $regex = "/[0-9]+:[\s](%s)+[\s]\[[a-zA-Z\.]+:[a-zA-Z]+\(\)\][\s]\{[0-9]+\}[\s][0-9]{4}-[0-9]{2}-[0-9]{2}[\s][0-9]{1,2}:[0-9]{2}:[0-9]{2}:[\s](%s).*$/is";

    public static function setUpBeforeClass(): void
    {
        self::$logger = Tlog::getInstance();

        self::$logger->setDestinations("Thelia\Log\Destination\TlogDestinationText");
        self::$logger->setLevel(Tlog::DEBUG);
        self::$logger->setFiles('*');
    }

    public function testDebugWithDebugLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::DEBUG);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, 'DEBUG', 'foo'));
        $logger->debug('foo');
    }

    public function testDebugWithoutDebugLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex('/^$/');
        $logger->debug('foo');
    }

    public function testDebugWithInfoLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::INFO);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, 'INFO', 'foo'));
        $logger->info('foo');
    }

    public function testDebugWithoutInfoLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex('/^$/');
        $logger->info('foo');
    }

    public function testDebugWithNoticeLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::NOTICE);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, 'NOTICE', 'foo'));
        $logger->notice('foo');
    }

    public function testDebugWithoutNoticeLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex('/^$/');
        $logger->notice('foo');
    }

    public function testWarningWithWarningLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::WARNING);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, 'WARNING', 'foo'));
        $logger->warning('foo');
    }

    public function testWarningWithoutWarningLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex('/^$/');
        $logger->warning('foo');
    }

    public function testErrorWithErrorLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::ERROR);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, 'ERROR', 'foo'));
        $logger->error('foo');
    }

    public function testErrorWithoutErrorLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex('/^$/');
        $logger->error('foo');
    }

    public function testErrorWithCriticalLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::CRITICAL);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, 'CRITICAL', 'foo'));
        $logger->critical('foo');
    }

    public function testErrorWithoutCriticalLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex('/^$/');
        $logger->critical('foo');
    }

    public function testErrorWithAlertLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::ALERT);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, 'ALERT', 'foo'));
        $logger->alert('foo');
    }

    public function testErrorWithoutAlertLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex('/^$/');
        $logger->alert('foo');
    }

    public function testErrorWithEmergencyLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::EMERGENCY);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, 'EMERGENCY', 'foo'));
        $logger->emergency('foo');
    }

    public function testErrorWithoutEmergencyLevel(): void
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex('/^$/');
        $logger->emergency('foo');
    }
}
