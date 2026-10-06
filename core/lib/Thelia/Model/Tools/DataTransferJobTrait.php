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

namespace Thelia\Model\Tools;

use Thelia\Domain\DataTransfer\Job\JobStatus;

/**
 * The status of an export or import job row.
 */
trait DataTransferJobTrait
{
    public function getJobStatus(): JobStatus
    {
        return JobStatus::tryFrom((string) $this->getStatus()) ?? JobStatus::FAILED;
    }

    public function isFinished(): bool
    {
        return $this->getJobStatus()->isFinished();
    }
}
