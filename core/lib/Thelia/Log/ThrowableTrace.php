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

namespace Thelia\Log;

use Thelia\Tools\TerminalText;

/**
 * How an exception goes to the log: Tlog casts every argument to a string, and
 * getTraceAsString() prints the arguments of each frame unless zend.exception_ignore_args is
 * on, one of which may be a connection password.
 */
final class ThrowableTrace
{
    /**
     * The frames of the trace without their arguments, one per line: a file or class name
     * cannot start a line of its own.
     */
    public static function withoutArguments(\Throwable $throwable): string
    {
        $frames = [];

        foreach ($throwable->getTrace() as $index => $frame) {
            $frames[] = \sprintf('#%d %s(%d): %s%s%s()', $index, TerminalText::singleLine($frame['file'] ?? '[internal function]'), $frame['line'] ?? 0, TerminalText::singleLine($frame['class'] ?? ''), $frame['type'] ?? '', TerminalText::singleLine($frame['function']));
        }

        return TerminalText::withoutControlCharacters(implode("\n", $frames));
    }

    /**
     * The class, the message on one line and the trace without arguments, then the same for
     * the exceptions it was caused by, at most ten: a database error usually hides its reason
     * there. That reason goes to the log as the database wrote it, a value of the row it
     * refused included: not for a write of personal data without a review.
     */
    public static function describe(\Throwable $throwable): string
    {
        $parts = [];

        for ($cause = $throwable; null !== $cause && \count($parts) < 10; $cause = $cause->getPrevious()) {
            $parts[] = ($cause === $throwable ? '' : 'Caused by ').TerminalText::singleLine($cause::class).': '.TerminalText::onOneLine($cause->getMessage())."\n".self::withoutArguments($cause);
        }

        if (null !== $cause) {
            $more = 0;

            for (; null !== $cause; $cause = $cause->getPrevious()) {
                ++$more;
            }

            $parts[] = \sprintf('... %d more causes', $more);
        }

        return implode("\n", $parts);
    }
}
