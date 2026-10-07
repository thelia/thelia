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
use Thelia\Domain\DataTransfer\Job\ImportStorage;
use Thelia\Domain\DataTransfer\Job\RunImportJob;
use Thelia\Log\Tlog;
use Thelia\Messenger\Event\FailedJobRemovedEvent;
use Thelia\Messenger\JobFailureMessage;
use Thelia\Model\ImportJobQuery;

/**
 * The uploaded file of a failed import is kept for its replay. Once an administrator
 * deleted the failure, there is no replay left: the file, personal data, goes at once
 * instead of waiting for the purge.
 */
final readonly class RemovedImportFileListener
{
    public function __construct(
        private ImportStorage $storage,
    ) {
    }

    #[AsEventListener]
    public function onFailedJobRemoved(FailedJobRemovedEvent $event): void
    {
        if (!$event->message instanceof RunImportJob) {
            return;
        }

        $job = ImportJobQuery::create()->findPk($event->message->importJobId);

        if (null === $job) {
            return;
        }

        // The failure is deleted already: a file left behind is the purge's to sweep.
        try {
            $this->storage->discardFileOf($job);
        } catch (\Throwable $leftBehind) {
            Tlog::getInstance()->addWarning(\sprintf('The file of import job %d was left behind: %s', $job->getId(), JobFailureMessage::forLog($leftBehind)));
        }
    }
}
