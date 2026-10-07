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

namespace Thelia\Messenger\Log;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Thelia\Messenger\JobFailureMessage;

/**
 * Keeps the text of a failed job out of the log of the workers.
 *
 * Messenger logs the message of the exception a job threw, and the exception itself,
 * when it retries the job or sets it aside: a database error quotes the values of a
 * row, a customer's among them, and the log is kept far longer than the failed jobs.
 * The exception is named by its class, code and place instead
 * ({@see JobFailureMessage::forLog()}).
 */
#[AsMonologProcessor(channel: 'messenger')]
final readonly class FailedJobLogProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $exception = $record->context['exception'] ?? null;

        if (!$exception instanceof \Throwable) {
            return $record;
        }

        $context = $record->context;
        unset($context['exception']);

        if (\array_key_exists('error', $context)) {
            $context['error'] = JobFailureMessage::forLog($exception);
        }

        return $record->with(context: $context);
    }
}
