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

namespace Thelia\Model;

use Thelia\Domain\DataTransfer\Job\ExportJobStatus;
use Thelia\Model\Base\ExportJob as BaseExportJob;

class ExportJob extends BaseExportJob
{
    public function getJobStatus(): ExportJobStatus
    {
        return ExportJobStatus::tryFrom((string) $this->getStatus()) ?? ExportJobStatus::FAILED;
    }

    public function isFinished(): bool
    {
        return $this->getJobStatus()->isFinished();
    }
}
