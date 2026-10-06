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

namespace Thelia\Messenger;

use Doctrine\DBAL\Exception as DbalException;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Messenger\Exception\TransportException;

/**
 * What an administrator reads of a failed job.
 *
 * The reason a job gives itself (no data to export, a file it cannot read, a command
 * that exited with an error) is shown as it is. The text of a database, transport or
 * PHP error is not: it quotes SQL, values, paths and host names. It is written to the
 * server log, and the administrator reads that the details are there.
 */
final class JobFailureMessage
{
    public const SERVER_ERROR = 'The job failed because of a server error. The details are in the server log.';

    private const MAX_LENGTH = 2000;

    public static function forAdministrator(\Throwable $exception): string
    {
        for ($cause = $exception; null !== $cause; $cause = $cause->getPrevious()) {
            if (self::isInfrastructure($cause)) {
                return self::SERVER_ERROR;
            }
        }

        return mb_substr($exception->getMessage(), 0, self::MAX_LENGTH);
    }

    private static function isInfrastructure(\Throwable $exception): bool
    {
        return $exception instanceof \PDOException
            || $exception instanceof \Error
            // A PHP warning or notice turned into an exception quotes paths of the
            // server. The core throws ErrorException with a message of its own too
            // (an export whose module is gone): those keep the default E_ERROR.
            || ($exception instanceof \ErrorException && \E_ERROR !== $exception->getSeverity())
            || $exception instanceof PropelException
            || $exception instanceof DbalException
            || $exception instanceof TransportException;
    }
}
