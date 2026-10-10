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

namespace Thelia\Tests\Unit\Messenger;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Thelia\Messenger\Log\FailedJobLogProcessor;

/**
 * Messenger logs what a job threw when it retries it or sets it aside: the text of
 * the exception, and the exception, never reach the log.
 */
final class FailedJobLogProcessorTest extends TestCase
{
    public function testTheTextOfAFailedJobIsNamedByItsClassAndPlace(): void
    {
        $exception = new \PDOException("SQLSTATE[23000]: Duplicate entry 'buyer@example.com'", 23000);
        $record = new LogRecord(new \DateTimeImmutable(), 'messenger', Level::Critical, 'Error thrown while handling message {class}. Error: "{error}"', [
            'class' => 'App\\Message\\SyncStock',
            'error' => $exception->getMessage(),
            'exception' => $exception,
        ]);

        $processed = (new FailedJobLogProcessor())($record);

        self::assertArrayNotHasKey('exception', $processed->context);
        self::assertStringStartsWith('PDOException (code 23000) at ', (string) $processed->context['error']);
        self::assertStringNotContainsString('buyer@example.com', json_encode($processed->context, \JSON_THROW_ON_ERROR));
    }

    public function testARecordWithoutAnExceptionIsLeftAlone(): void
    {
        $record = new LogRecord(new \DateTimeImmutable(), 'messenger', Level::Info, 'Received message {class}', ['class' => 'App\\Message\\SyncStock']);

        self::assertSame($record, (new FailedJobLogProcessor())($record));
    }
}
