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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Thelia\Log\Destination\TlogDestinationText;
use Thelia\Log\Tlog;

/**
 * Tlog writes a message only when its level reaches the configured threshold and
 * its caller passes the IP and source file filters; each argument given to an
 * add*() shortcut becomes a line of its own.
 */
final class TlogTest extends TestCase
{
    private ?string $remoteAddress = null;

    protected function setUp(): void
    {
        $this->remoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        unset($_SERVER['REMOTE_ADDR']);
    }

    protected function tearDown(): void
    {
        if (null === $this->remoteAddress) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $this->remoteAddress;
        }
    }

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

    public function testEachLineIsNumberedAndNamesTheFileAndFunctionItComesFrom(): void
    {
        $this->expectOutputString(
            '1 WARNING [TlogTest.php:'.__FUNCTION__."()] First\n"
            .'2 WARNING [TlogTest.php:'.__FUNCTION__."()] Second\n",
        );

        $this->loggerAt(Tlog::DEBUG, '#INDEX #LEVEL [#FILE:#FUNCTION()] ')->addWarning('First', 'Second');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function levelMethodProvider(): iterable
    {
        yield 'debug' => ['debug', 'DEBUG'];
        yield 'info' => ['info', 'INFO'];
        yield 'notice' => ['notice', 'NOTICE'];
        yield 'warning' => ['warning', 'WARNING'];
        yield 'error' => ['error', 'ERROR'];
        yield 'err' => ['err', 'ERROR'];
        yield 'critical' => ['critical', 'CRITICAL'];
        yield 'crit' => ['crit', 'CRITICAL'];
        yield 'alert' => ['alert', 'ALERT'];
        yield 'emergency' => ['emergency', 'EMERGENCY'];
    }

    #[DataProvider('levelMethodProvider')]
    public function testEachLevelMethodWritesItsOwnLevel(string $method, string $label): void
    {
        $this->expectOutputString($label.": Something happened\n");

        $this->loggerAt(Tlog::DEBUG)->{$method}('Something happened');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function addMethodProvider(): iterable
    {
        yield 'addDebug' => ['addDebug', 'DEBUG'];
        yield 'addInfo' => ['addInfo', 'INFO'];
        yield 'addNotice' => ['addNotice', 'NOTICE'];
        yield 'addWarning' => ['addWarning', 'WARNING'];
        yield 'addError' => ['addError', 'ERROR'];
        yield 'addCritical' => ['addCritical', 'CRITICAL'];
        yield 'addAlert' => ['addAlert', 'ALERT'];
        yield 'addEmergency' => ['addEmergency', 'EMERGENCY'];
    }

    #[DataProvider('addMethodProvider')]
    public function testEachAddMethodWritesItsOwnLevel(string $method, string $label): void
    {
        $this->expectOutputString($label.": First\n".$label.": Second\n");

        $this->loggerAt(Tlog::DEBUG)->{$method}('First', 'Second');
    }

    public function testAThresholdGivenAsAStringIsUnderstood(): void
    {
        $this->expectOutputString("ERROR: Payment refused\n");

        $logger = $this->loggerAt(Tlog::DEBUG)->setLevel('400');
        $logger->info('Cart updated');
        $logger->error('Payment refused');
    }

    public function testContextValuesReplaceTheirPlaceholders(): void
    {
        $this->expectOutputString("ERROR: Order 12 refused for Ada, {missing} stays\n");

        $this->loggerAt(Tlog::DEBUG)->error('Order {id} refused for {name}, {missing} stays', ['id' => 12, 'name' => 'Ada']);
    }

    // interpolate() is part of what a subclass inherits; its result is covered above.
    public function testASubclassCanInterpolateAMessage(): void
    {
        $logger = new class extends Tlog {
            public function __construct()
            {
            }

            public function interpolation(string $message, array $context): string
            {
                return $this->interpolate($message, $context);
            }
        };

        self::assertSame('Order 12 refused', $logger->interpolation('Order {id} refused', ['id' => 12]));
    }

    public function testAnEmptyIpFilterKeepsTheLogger(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        $this->expectOutputString("ERROR: Kept\n");

        $this->loggerAt(Tlog::DEBUG)->setIp('')->error('Kept');
    }

    public function testAZeroIpFilterKeepsTheLogger(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        $this->expectOutputString("ERROR: Kept\n");

        $this->loggerAt(Tlog::DEBUG)->setIp('0')->error('Kept');
    }

    public function testAnIpFilterWithoutARequestKeepsTheLogger(): void
    {
        $this->expectOutputString("ERROR: Kept\n");

        $this->loggerAt(Tlog::DEBUG)->setIp('10.0.0.1')->error('Kept');
    }

    public function testAnIpInTheFilterKeepsTheLogger(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        $this->expectOutputString("ERROR: Kept\n");

        $this->loggerAt(Tlog::DEBUG)->setIp('10.0.0.1;10.0.0.2')->error('Kept');
    }

    public function testAnIpOutsideTheFilterSilencesTheLogger(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.3';
        $this->expectOutputString('');

        $logger = $this->loggerAt(Tlog::DEBUG)->setIp('10.0.0.1;10.0.0.2');
        $logger->error('Dropped');
        $logger->emergency('Dropped too');
    }

    public function testASourceFileIsActiveWhenAllFilesAreEnabled(): void
    {
        $logger = $this->loggerAt(Tlog::DEBUG)->setFiles('*');

        self::assertTrue($logger->isActivedFile('Cart.php'));
    }

    public function testOnlyTheListedSourceFilesAreActive(): void
    {
        $logger = $this->loggerAt(Tlog::DEBUG)->setFiles('Cart.php;Order.php');

        self::assertTrue($logger->isActivedFile('Cart.php'));
        self::assertTrue($logger->isActivedFile('Order.php'));
        self::assertFalse($logger->isActivedFile('Product.php'));
    }

    public function testAnExcludedSourceFileIsInactive(): void
    {
        $logger = $this->loggerAt(Tlog::DEBUG)->setFiles('*;!Cart.php');

        self::assertFalse($logger->isActivedFile('Cart.php'));
        self::assertTrue($logger->isActivedFile('Order.php'));
    }

    public function testAListedSourceFileCanBeExcludedToo(): void
    {
        $logger = $this->loggerAt(Tlog::DEBUG)->setFiles('Cart.php;!Cart.php;Order.php');

        self::assertFalse($logger->isActivedFile('Cart.php'));
        self::assertTrue($logger->isActivedFile('Order.php'));
    }

    public function testALineOfAFilteredOutSourceFileIsDropped(): void
    {
        $this->expectOutputString('');

        $this->loggerAt(Tlog::DEBUG)->setFiles('Cart.php')->error('Dropped');
    }

    public function testALineOfAnExcludedSourceFileIsDropped(): void
    {
        $this->expectOutputString('');

        $this->loggerAt(Tlog::DEBUG)->setFiles('*;!TlogTest.php')->error('Dropped');
    }

    public function testALevelIsActivatedFromTheThresholdUp(): void
    {
        $logger = $this->loggerAt(Tlog::WARNING);

        self::assertFalse($logger->isActivated(Tlog::INFO));
        self::assertTrue($logger->isActivated(Tlog::WARNING));
        self::assertTrue($logger->isActivated(Tlog::ERROR));
    }

    public function testALevelIsNotActivatedForAFilteredOutCaller(): void
    {
        $logger = $this->loggerAt(Tlog::DEBUG)->setFiles('Cart.php');

        self::assertFalse($logger->isActivated(Tlog::ERROR));
    }

    public function testALevelIsActivatedForAListedCaller(): void
    {
        $logger = $this->loggerAt(Tlog::DEBUG)->setFiles('TlogTest.php');

        self::assertTrue($logger->isActivated(Tlog::ERROR));
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
    private function loggerAt(int $level, string $prefix = '#LEVEL: '): Tlog
    {
        $logger = (new \ReflectionClass(Tlog::class))->newInstanceWithoutConstructor();

        return $logger
            ->setLevel($level)
            ->setFiles('*')
            ->setPrefix($prefix)
            ->setDestinations(TlogDestinationText::class);
    }
}
