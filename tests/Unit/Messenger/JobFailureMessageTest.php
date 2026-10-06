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

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Thelia\Domain\DataTransfer\Exception\DataTransferNoDataFoundException;
use Thelia\Domain\DataTransfer\Exception\HandlerUnavailableException;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Messenger\JobFailureMessage;

/**
 * Only the words written for the administrator reach the screen; the log names any
 * other exception by its class, code and place.
 */
final class JobFailureMessageTest extends TestCase
{
    public function testTheReasonAJobGivesItselfIsShownAsItIs(): void
    {
        self::assertSame('No data found for your export.', JobFailureMessage::forAdministrator(new DataTransferNoDataFoundException('No data found for your export.')));
        self::assertSame('The extension "exe" is not allowed', JobFailureMessage::forAdministrator(new FormValidationException('The extension "exe" is not allowed')));
        self::assertSame('The export "x" cannot be run.', JobFailureMessage::forAdministrator(new HandlerUnavailableException('The export "x" cannot be run.')));
    }

    public function testAReasonWrappedByTheBusIsStillShown(): void
    {
        $wrapped = new HandlerFailedException(new Envelope(new \stdClass()), [new DataTransferNoDataFoundException('No data found for your export.')]);

        self::assertSame('No data found for your export.', JobFailureMessage::forAdministrator($wrapped));
    }

    public function testALongReasonIsCut(): void
    {
        self::assertSame(2000, mb_strlen(JobFailureMessage::forAdministrator(new FormValidationException(str_repeat('é', 3000)))));
    }

    /**
     * Anything else may quote a query, a value or a path: the administrator is sent to
     * the server log.
     */
    public function testAnyOtherExceptionIsNotShown(): void
    {
        foreach ([
            new PropelException('SQLSTATE[23000]: INSERT INTO customer (email) VALUES (\'buyer@example.com\')'),
            new \PDOException('SQLSTATE[HY000] [2002] Connection refused'),
            new \TypeError('Argument #1 ($path) must be of type string, null given in /var/www/html/core/lib/Thelia/X.php'),
            new \ErrorException('unlink(/var/www/html/var/data-transfer/import/x.csv): No such file or directory', 0, \E_WARNING),
            new FileException('Could not move the file "/tmp/phpA1" to "/var/www/html/var/data-transfer/import/x.csv".'),
            new \RuntimeException('File /var/www/html/var/x.zip doesn\'t exists'),
        ] as $exception) {
            self::assertSame(JobFailureMessage::SERVER_ERROR, JobFailureMessage::forAdministrator($exception), $exception::class);
        }
    }

    public function testTheLogNamesAnyOtherExceptionWithoutItsText(): void
    {
        $exception = new \RuntimeException('Import failed', 0, new \PDOException('Duplicate entry \'buyer@example.com\'', 23000));

        $logged = JobFailureMessage::forLog($exception);

        self::assertStringContainsString('PDOException (code 23000) at ', $logged);
        self::assertStringNotContainsString('buyer@example.com', $logged);
    }

    public function testTheLogKeepsTheWordsWrittenForTheAdministrator(): void
    {
        self::assertSame('No data found for your export.', JobFailureMessage::forLog(new DataTransferNoDataFoundException('No data found for your export.')));
    }
}
