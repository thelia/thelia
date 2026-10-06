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
use Thelia\Messenger\JobFailureMessage;

final class JobFailureMessageTest extends TestCase
{
    public function testTheReasonAnExportGivesItselfIsShownAsItIs(): void
    {
        self::assertSame('No data to export.', JobFailureMessage::forAdministrator(new \RuntimeException('No data to export.')));
    }

    public function testALongReasonIsCut(): void
    {
        self::assertSame(2000, mb_strlen(JobFailureMessage::forAdministrator(new \RuntimeException(str_repeat('é', 3000)))));
    }

    /**
     * A database error quotes the query and its values: the administrator is sent to
     * the server log, wherever the error sits in the chain.
     */
    public function testADatabaseErrorIsNotShown(): void
    {
        $wrapped = new \RuntimeException('Import failed', 0, new PropelException('SQLSTATE[23000]: INSERT INTO customer (email) VALUES (\'buyer@example.com\')'));

        self::assertSame(JobFailureMessage::SERVER_ERROR, JobFailureMessage::forAdministrator($wrapped));
        self::assertSame(JobFailureMessage::SERVER_ERROR, JobFailureMessage::forAdministrator(new \PDOException('SQLSTATE[HY000] [2002] Connection refused')));
    }

    public function testAPhpErrorIsNotShown(): void
    {
        self::assertSame(JobFailureMessage::SERVER_ERROR, JobFailureMessage::forAdministrator(new \TypeError('Argument #1 ($path) must be of type string, null given in /var/www/html/core/lib/Thelia/X.php')));
    }
}
