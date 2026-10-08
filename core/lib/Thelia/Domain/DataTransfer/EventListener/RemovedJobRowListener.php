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

namespace Thelia\Domain\DataTransfer\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Domain\DataTransfer\Job\DataTransferJobMessage;
use Thelia\Domain\DataTransfer\Job\JobStatus;
use Thelia\Domain\DataTransfer\Job\RunImportJob;
use Thelia\Log\Tlog;
use Thelia\Messenger\Event\FailedJobRemovedEvent;
use Thelia\Messenger\JobFailureMessage;
use Thelia\Model\ExportJobQuery;
use Thelia\Model\ImportJobQuery;

/**
 * A job set aside before it was taken (the database gone at that moment) kept its row
 * waiting for a worker. Once its failure is deleted, no worker will ever come: the row
 * says what became of it instead of waiting until the purge.
 */
final readonly class RemovedJobRowListener
{
    public const DELETED = 'Deleted from the failed jobs before it ran.';

    #[AsEventListener]
    public function onFailedJobRemoved(FailedJobRemovedEvent $event): void
    {
        $message = $event->message;

        if (!$message instanceof DataTransferJobMessage) {
            return;
        }

        // The failure is deleted already: a row left waiting is the purge's to sweep.
        try {
            // On a condition: a worker that took the job meanwhile keeps it running.
            $query = $message instanceof RunImportJob ? ImportJobQuery::create() : ExportJobQuery::create();
            $query->filterById($message->jobId())
                ->filterByStatus(JobStatus::QUEUED->value)
                ->update(['Status' => JobStatus::FAILED->value, 'Error' => self::DELETED, 'FinishedAt' => new \DateTime()]);
        } catch (\Throwable $notRecorded) {
            Tlog::getInstance()->addWarning(\sprintf('%s was deleted from the failed jobs but its row could not be marked failed: %s', $message->describe(), JobFailureMessage::forLog($notRecorded)));
        }
    }
}
