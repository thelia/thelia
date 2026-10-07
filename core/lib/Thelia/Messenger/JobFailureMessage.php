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

use Thelia\Exception\UserFacingFailure;

/**
 * What an administrator, and the server log, read of a failed job.
 *
 * Only an exception written for the administrator ({@see UserFacingFailure}) is shown
 * as it is. Any other may quote SQL, the values of a row, paths or host names: the
 * administrator reads that the job failed and that the details are in the server
 * log, and the log names the exception by its class, code and place, never by a text
 * that may hold the personal data of a customer.
 */
final class JobFailureMessage
{
    public const SERVER_ERROR = 'The job failed because of a server error. The details are in the server log.';

    private const MAX_LENGTH = 2000;

    public static function forAdministrator(\Throwable $exception): string
    {
        $userFacing = self::userFacingCause($exception);

        return null === $userFacing ? self::SERVER_ERROR : mb_substr($userFacing->getMessage(), 0, self::MAX_LENGTH);
    }

    /**
     * The exception as the log names it: its own words when they were written for the
     * administrator, its class, code and place otherwise.
     */
    public static function forLog(\Throwable $exception): string
    {
        $userFacing = self::userFacingCause($exception);

        if (null !== $userFacing) {
            // On one line: a message may quote what was typed, line breaks included.
            return (string) preg_replace('/\s+/', ' ', $userFacing->getMessage());
        }

        $cause = $exception;
        while (null !== $cause->getPrevious()) {
            $cause = $cause->getPrevious();
        }

        return \sprintf('%s (code %s) at %s:%d', $cause::class, (string) $cause->getCode(), $cause->getFile(), $cause->getLine());
    }

    private static function userFacingCause(\Throwable $exception): ?\Throwable
    {
        for ($cause = $exception; null !== $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof UserFacingFailure) {
                return $cause;
            }
        }

        return null;
    }
}
