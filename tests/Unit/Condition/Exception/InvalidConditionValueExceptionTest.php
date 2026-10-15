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

namespace Thelia\Tests\Unit\Condition\Exception;

use PHPUnit\Framework\TestCase;
use Thelia\Condition\Exception\InvalidConditionValueException;
use Thelia\Condition\Implementation\MatchForXArticles;
use Thelia\Log\Destination\TlogDestinationNull;
use Thelia\Log\Destination\TlogDestinationText;
use Thelia\Log\Tlog;
use Thelia\Tests\Support\Log\SilencedTlog;

/**
 * The back office answers a missing or invalid condition value with a generic message
 * and relies on the exception to write the detail to the shop log: which condition,
 * which parameter.
 */
final class InvalidConditionValueExceptionTest extends TestCase
{
    use SilencedTlog;

    protected function tearDown(): void
    {
        $this->restoreTlog();
    }

    public function testTheMessageNamesTheConditionAndTheParameter(): void
    {
        $this->logTo(TlogDestinationNull::class);

        $exception = new InvalidConditionValueException(MatchForXArticles::class, 'quantity');

        self::assertSame(
            'Invalid Parameter for Condition '.MatchForXArticles::class.' on parameter quantity',
            $exception->getMessage(),
        );
    }

    public function testTheMessageIsWrittenToTheShopLogAsAnError(): void
    {
        $this->logTo(TlogDestinationText::class);
        $this->expectOutputString('ERROR: Invalid Parameter for Condition '.MatchForXArticles::class." on parameter quantity\n");

        new InvalidConditionValueException(MatchForXArticles::class, 'quantity');
    }

    /**
     * Tlog::getInstance() reads its configuration from the database: the exception is
     * handed a logger built bare instead, writing every level with its name in front.
     */
    private function logTo(string $destination): void
    {
        $logger = (new \ReflectionClass(Tlog::class))->newInstanceWithoutConstructor()
            ->setLevel(Tlog::DEBUG)
            ->setFiles('*')
            ->setPrefix('#LEVEL: ')
            ->setDestinations($destination);

        (new \ReflectionProperty(Tlog::class, 'instance'))->setValue(null, $logger);
    }
}
