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

use Thelia\Domain\DataTransfer\Job\JobStatus;
use Thelia\Model\Base\ImportJob as BaseImportJob;

class ImportJob extends BaseImportJob
{
    public function getJobStatus(): JobStatus
    {
        return JobStatus::tryFrom((string) $this->getStatus()) ?? JobStatus::FAILED;
    }

    /**
     * Where the uploaded file is on this server. The row keeps a path relative to the
     * project, so it stays short and survives the project being moved.
     */
    public function getStoredFilePath(): string
    {
        $path = (string) $this->getFilePath();

        return str_starts_with($path, '/') ? $path : THELIA_ROOT.$path;
    }

    public function isFinished(): bool
    {
        return $this->getJobStatus()->isFinished();
    }

    /**
     * The rows the import refused, each with its reason.
     *
     * @return list<string>
     */
    public function getRowErrorList(): array
    {
        $errors = json_decode((string) $this->getRowErrors(), true);

        return \is_array($errors) ? array_values(array_map('strval', $errors)) : [];
    }
}
